<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// Run automatic backup safely. Backup problems must never stop dashboard login.
try {
    require_once __DIR__ . '/../backup/auto_backup.php';
} catch (Throwable $e) {
    // Hidden for customer delivery. Backup page can still be used manually.
}

function dashboard_scalar(mysqli $conn, string $sql, string $field, $default = 0) {
    $result = $conn->query($sql);
    if (!$result) {
        return $default;
    }
    $row = $result->fetch_assoc();
    return $row[$field] ?? $default;
}

$in_stock = dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM cars WHERE status='IN_STOCK'", 'total');
$sold = dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM cars WHERE status='SOLD'", 'total');
$capital = dashboard_scalar($conn, "SELECT IFNULL(SUM(purchase_price),0) AS total FROM cars WHERE status='IN_STOCK'", 'total');
$total_costs = dashboard_scalar($conn, "SELECT IFNULL(SUM(amount),0) AS total FROM car_costs", 'total');
$profit = dashboard_scalar($conn, "
    SELECT IFNULL(SUM(cs.sale_price - (c.purchase_price + IFNULL(cc.total_cost,0))),0) AS total_profit
    FROM cars c
    JOIN car_sales cs ON c.id = cs.car_id
    LEFT JOIN (
        SELECT car_id, SUM(amount) total_cost
        FROM car_costs
        GROUP BY car_id
    ) cc ON c.id = cc.car_id
", 'total_profit');
$old_stock = dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM cars WHERE status='IN_STOCK' AND purchase_date <= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", 'total');
$active_leads = dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM leads WHERE lead_stage NOT IN ('WON','LOST')", 'total');
$active_reservations = dashboard_scalar($conn, "SELECT COUNT(*) AS total FROM reservations WHERE status='ACTIVE' AND (expiry_date IS NULL OR expiry_date>=CURDATE())", 'total');
$receivables = dashboard_scalar($conn, "SELECT IFNULL(SUM(cs.sale_price-IFNULL(p.paid,0)),0) total FROM car_sales cs LEFT JOIN (SELECT sale_id,SUM(amount) paid FROM sale_payments GROUP BY sale_id)p ON p.sale_id=cs.id WHERE cs.sale_price>IFNULL(p.paid,0)", 'total');
$pending_commissions = dashboard_scalar($conn, "SELECT IFNULL(SUM(amount),0) total FROM dalali_commissions WHERE status='PENDING'", 'total');

$recent = $conn->query("
    SELECT
        c.id,
        c.car_name,
        c.plate_number,
        c.purchase_price,
        c.purchase_date,
        c.status,
        IFNULL(cc.total_cost,0) total_cost,
        cs.sale_price,
        (IFNULL(cs.sale_price,0) - (c.purchase_price + IFNULL(cc.total_cost,0))) profit
    FROM cars c
    LEFT JOIN (
        SELECT car_id, SUM(amount) total_cost
        FROM car_costs
        GROUP BY car_id
    ) cc ON c.id = cc.car_id
    LEFT JOIN car_sales cs ON c.id = cs.car_id
    ORDER BY c.created_at DESC
    LIMIT 5
");

$page_title = 'Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/leftsidebar.php';
?>

<style>
    .clean-dashboard {
        background: #f5f6f8;
        min-height: 100vh;
        color: #1f2933;
    }

    .dashboard-head {
        margin-bottom: 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }

    .dashboard-head h1 {
        margin: 0;
        font-size: 26px;
        font-weight: 700;
        color: #1f2933;
    }

    .dashboard-head p {
        margin: 5px 0 0;
        font-size: 14px;
        color: #6b7280;
    }

    .dashboard-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .dash-btn {
        display: inline-block;
        padding: 9px 15px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid #d1d5db;
        color: #374151;
        background: #ffffff;
    }

    .dash-btn.primary {
        background: #1f4e79;
        border-color: #1f4e79;
        color: #ffffff;
    }

    .dash-btn:hover {
        opacity: 0.9;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 14px;
        margin-bottom: 22px;
    }

    .summary-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 18px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.035);
    }

    .summary-card .line {
        width: 35px;
        height: 3px;
        background: #1f4e79;
        border-radius: 10px;
        margin-bottom: 14px;
    }

    .summary-card.green .line {
        background: #2e7d32;
    }

    .summary-card.orange .line {
        background: #b45309;
    }

    .summary-card.purple .line {
        background: #5b4b8a;
    }

    .summary-card.red .line {
        background: #9f1239;
    }

    .summary-card p {
        margin: 0;
        color: #6b7280;
        font-size: 13px;
        font-weight: 600;
    }

    .summary-card h2 {
        margin: 8px 0 0;
        color: #111827;
        font-size: 22px;
        font-weight: 700;
    }

    .dashboard-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.035);
    }

    .card-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .card-head h2 {
        margin: 0;
        font-size: 18px;
        color: #1f2933;
        font-weight: 700;
    }

    .professional-table {
        width: 100%;
        border-collapse: collapse;
    }

    .professional-table th {
        text-align: left;
        padding: 12px 10px;
        font-size: 12px;
        color: #6b7280;
        font-weight: 700;
        border-bottom: 1px solid #e5e7eb;
        background: #f9fafb;
    }

    .professional-table td {
        padding: 13px 10px;
        font-size: 14px;
        color: #374151;
        border-bottom: 1px solid #eef0f3;
    }

    .professional-table tbody tr:hover {
        background: #fafafa;
    }

    .car-title {
        font-weight: 700;
        color: #111827;
    }

    .plate-number {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 5px;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        font-size: 12px;
        font-weight: 700;
        color: #374151;
    }

    .status-badge {
        display: inline-block;
        padding: 5px 9px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
    }

    .status-badge.stock {
        background: #ecfdf3;
        color: #166534;
    }

    .status-badge.sold {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .view-link {
        display: inline-block;
        padding: 6px 10px;
        border-radius: 5px;
        background: #1f4e79;
        color: #ffffff;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
    }

    .view-link:hover {
        opacity: 0.9;
    }

    .empty-message {
        text-align: center;
        color: #6b7280;
        padding: 22px !important;
    }

    @media screen and (max-width: 1200px) {
        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media screen and (max-width: 700px) {
        .dashboard-head {
            flex-direction: column;
            align-items: flex-start;
        }

        .summary-grid {
            grid-template-columns: 1fr;
        }

        .dashboard-card {
            padding: 15px;
        }

        .card-head {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="content-page clean-dashboard">

    <div class="dashboard-head">
        <div>
            <h1>Dashboard</h1>
            <p>Stock, sales, capital, and recent vehicle activity.</p>
        </div>

        <div class="dashboard-actions">
            <a class="dash-btn primary" href="<?= app_url('backend/cars/add.php'); ?>">Add Car</a>
            <a class="dash-btn" href="<?= app_url('backend/reports/index.php'); ?>">Reports</a>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="line"></div>
            <p>Cars In Stock</p>
            <h2><?= number_format((float)$in_stock); ?></h2>
        </div>

        <div class="summary-card green">
            <div class="line"></div>
            <p>Sold Cars</p>
            <h2><?= number_format((float)$sold); ?></h2>
        </div>

        <div class="summary-card orange">
            <div class="line"></div>
            <p>Capital In Stock</p>
            <h2><?= money($capital); ?></h2>
        </div>

        <div class="summary-card purple">
            <div class="line"></div>
            <p>Total Profit</p>
            <h2><?= money($profit); ?></h2>
        </div>

        <div class="summary-card red">
            <div class="line"></div>
            <p>Old Stock 30+ Days</p>
            <h2><?= number_format((float)$old_stock); ?></h2>
        </div>
    </div>

    <div class="dashboard-card" style="margin-bottom:22px;">
        <div class="card-head"><h2>Sales Operations</h2><span class="muted">Customer pipeline, reservations and money still to collect/pay.</span></div>
        <div class="summary-grid" style="margin-bottom:0;grid-template-columns:repeat(4,1fr);">
            <div class="summary-card"><div class="line"></div><p>Active Leads</p><h2><?= number_format((float)$active_leads); ?></h2></div>
            <div class="summary-card green"><div class="line"></div><p>Active Reservations</p><h2><?= number_format((float)$active_reservations); ?></h2></div>
            <div class="summary-card orange"><div class="line"></div><p>Customer Receivables</p><h2><?= money($receivables); ?></h2></div>
            <div class="summary-card purple"><div class="line"></div><p>Pending Dalali Commission</p><h2><?= money($pending_commissions); ?></h2></div>
        </div>
    </div>

    <div class="dashboard-card">
        <div class="card-head">
            <h2>Recent Cars</h2>
            <a class="dash-btn" href="<?= app_url('backend/cars/list.php'); ?>">View All Cars</a>
        </div>

        <div class="table-responsive">
            <table class="professional-table">
                <thead>
                    <tr>
                        <th>Car</th>
                        <th>Plate</th>
                        <th>Purchase</th>
                        <th>Sale</th>
                        <th>Profit</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($recent && $recent->num_rows): ?>
                        <?php while ($r = $recent->fetch_assoc()): $isSold = $r['status'] === 'SOLD'; ?>
                            <tr>
                                <td>
                                    <span class="car-title"><?= e($r['car_name']); ?></span>
                                </td>

                                <td>
                                    <span class="plate-number"><?= e(car_sales_plate_label($r['plate_number'])); ?></span>
                                </td>

                                <td><?= money($r['purchase_price']); ?></td>

                                <td>
                                    <?= $r['sale_price'] !== null ? money($r['sale_price']) : '—'; ?>
                                </td>

                                <td>
                                    <?= $isSold ? money($r['profit']) : '—'; ?>
                                </td>

                                <td>
                                    <span class="status-badge <?= $isSold ? 'sold' : 'stock'; ?>">
                                        <?= $isSold ? 'Sold' : 'In Stock'; ?>
                                    </span>
                                </td>

                                <td>
                                    <a class="view-link" href="<?= app_url('backend/cars/view.php?id=' . $r['id']); ?>">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-message">No cars found. Add your first car.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>