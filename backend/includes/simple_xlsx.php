<?php
/**
 * Small XLSX/CSV reader for showroom migration files.
 * Reads the first worksheet. It does not require ZipArchive or SimpleXML.
 */
function car_sales_column_index_from_ref(string $ref): int {
    if(!preg_match('/^([A-Z]+)/i',$ref,$m)) return 0;
    $letters=strtoupper($m[1]);$n=0;
    for($i=0;$i<strlen($letters);$i++){$n=$n*26+(ord($letters[$i])-64);}
    return max(0,$n-1);
}
function car_sales_xml_decode(string $value): string {
    return html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_XML1,'UTF-8');
}
function car_sales_u16(string $s,int $o): int{$a=unpack('v',substr($s,$o,2));return (int)($a[1]??0);}
function car_sales_u32(string $s,int $o): int{$a=unpack('V',substr($s,$o,4));return (int)($a[1]??0);}
function car_sales_zip_get_pure(string $path,array $wanted): array {
    $data=@file_get_contents($path);if($data===false)throw new RuntimeException('Could not read Excel file.');
    $tailStart=max(0,strlen($data)-66000);$tail=substr($data,$tailStart);$rel=strrpos($tail,"PK\x05\x06");
    if($rel===false)throw new RuntimeException('Invalid XLSX/ZIP file.');$eocd=$tailStart+$rel;
    $entries=car_sales_u16($data,$eocd+10);$cdOffset=car_sales_u32($data,$eocd+16);$pos=$cdOffset;$index=[];
    for($i=0;$i<$entries && $pos+46<=strlen($data);$i++){
        if(substr($data,$pos,4)!=="PK\x01\x02")break;
        $method=car_sales_u16($data,$pos+10);$compSize=car_sales_u32($data,$pos+20);$nameLen=car_sales_u16($data,$pos+28);$extraLen=car_sales_u16($data,$pos+30);$commentLen=car_sales_u16($data,$pos+32);$localOffset=car_sales_u32($data,$pos+42);$name=substr($data,$pos+46,$nameLen);
        if(isset($wanted[$name]))$index[$name]=[$method,$compSize,$localOffset];
        $pos+=46+$nameLen+$extraLen+$commentLen;
    }
    $out=[];
    foreach($wanted as $name=>$_){
        if(!isset($index[$name])){$out[$name]=false;continue;}
        [$method,$compSize,$local]=$index[$name];if(substr($data,$local,4)!=="PK\x03\x04"){$out[$name]=false;continue;}
        $nameLen=car_sales_u16($data,$local+26);$extraLen=car_sales_u16($data,$local+28);$start=$local+30+$nameLen+$extraLen;$compressed=substr($data,$start,$compSize);
        if($method===0)$content=$compressed;elseif($method===8){$content=@gzinflate($compressed);if($content===false)throw new RuntimeException('Could not decompress Excel worksheet.');}else throw new RuntimeException('Unsupported XLSX compression method.');
        $out[$name]=$content;
    }
    return $out;
}
function car_sales_zip_read_needed(string $path,array $names): array {
    if(class_exists('ZipArchive')){
        $zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('Could not open Excel file.');$out=[];foreach($names as $n)$out[$n]=$zip->getFromName($n);$zip->close();return $out;
    }
    return car_sales_zip_get_pure($path,array_fill_keys($names,true));
}
function car_sales_parse_shared_strings($xml): array {
    if($xml===false||$xml==='')return [];$out=[];
    if(preg_match_all('/<(?:\w+:)?si\b[^>]*>(.*?)<\/(?:\w+:)?si>/si',$xml,$items)){
        foreach($items[1] as $body){$text='';if(preg_match_all('/<(?:\w+:)?t\b[^>]*>(.*?)<\/(?:\w+:)?t>/si',$body,$parts)){foreach($parts[1] as $part)$text.=car_sales_xml_decode($part);}else{$text=car_sales_xml_decode($body);}$out[]=$text;}
    }
    return $out;
}
function car_sales_read_xlsx(string $path): array {
    // Standard Excel files keep the first worksheet here. This also works for the supplied templates.
    $files=car_sales_zip_read_needed($path,['xl/sharedStrings.xml','xl/worksheets/sheet1.xml']);
    $raw=$files['xl/worksheets/sheet1.xml']??false;if($raw===false)throw new RuntimeException('No readable first worksheet found in Excel file.');
    $shared=car_sales_parse_shared_strings($files['xl/sharedStrings.xml']??false);$rows=[];
    if(!preg_match_all('/<(?:\w+:)?row\b[^>]*>(.*?)<\/(?:\w+:)?row>/si',$raw,$rowMatches))return [];
    foreach($rowMatches[1] as $rowBody){$cells=[];$max=-1;
        if(preg_match_all('/<((?:\w+:)?c)\b([^>]*?)(?:\/>|>(.*?)<\/\1>)/si',$rowBody,$cellMatches,PREG_SET_ORDER)){
            foreach($cellMatches as $cm){$attrs=$cm[2];$body=$cm[3]??'';$ref='';$type='';if(preg_match('/\br\s*=\s*["\']([^"\']+)["\']/i',$attrs,$m))$ref=$m[1];if(preg_match('/\bt\s*=\s*["\']([^"\']+)["\']/i',$attrs,$m))$type=$m[1];$idx=car_sales_column_index_from_ref($ref);$value='';
                if($type==='inlineStr'){if(preg_match_all('/<(?:\w+:)?t\b[^>]*>(.*?)<\/(?:\w+:)?t>/si',$body,$tm)){foreach($tm[1] as $part)$value.=car_sales_xml_decode($part);}}
                else{$v='';if(preg_match('/<(?:\w+:)?v\b[^>]*>(.*?)<\/(?:\w+:)?v>/si',$body,$vm))$v=car_sales_xml_decode($vm[1]);if($type==='s')$value=$shared[(int)$v]??'';elseif($type==='b')$value=$v==='1'?'1':'0';else $value=$v;}
                $cells[$idx]=$value;$max=max($max,$idx);
            }
        }
        if($max>=0){$line=[];for($i=0;$i<=$max;$i++)$line[]=$cells[$i]??'';$rows[]=$line;}
    }
    return $rows;
}
function car_sales_read_tabular_file(string $path,string $originalName): array {
    $ext=strtolower(pathinfo($originalName,PATHINFO_EXTENSION));
    if($ext==='xlsx')return car_sales_read_xlsx($path);
    if($ext==='csv'){$fh=fopen($path,'rb');if(!$fh)throw new RuntimeException('Could not read CSV file.');$rows=[];while(($r=fgetcsv($fh))!==false)$rows[]=$r;fclose($fh);return $rows;}
    throw new RuntimeException('Use an Excel .xlsx file or CSV. Older .xls files should be opened in Excel and saved as .xlsx first.');
}
function car_sales_normalize_header(string $value): string{$value=strtolower(trim($value));$value=preg_replace('/[^a-z0-9]+/','_',$value);return trim((string)$value,'_');}
function car_sales_rows_to_records(array $rows): array {if(!$rows)return [];$headers=array_map('car_sales_normalize_header',$rows[0]);$records=[];foreach(array_slice($rows,1) as $row){$record=[];$has=false;foreach($headers as $i=>$h){if($h==='')continue;$v=trim((string)($row[$i]??''));if($v!=='')$has=true;$record[$h]=$v;}if($has)$records[]=$record;}return $records;}
?>
