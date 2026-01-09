<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

// Fetch stock entries with related PO number and part name
$entries = $pdo->query(
    "SELECT se.id, se.po_id, se.part_no, COALESCE(pm.part_name, '') AS part_name, se.received_qty, se.invoice_no, se.status, se.received_date, se.remarks, po.po_no
     FROM stock_entries se
     LEFT JOIN purchase_orders po ON se.po_id = po.id
     LEFT JOIN part_master pm ON se.part_no = pm.part_no
     ORDER BY se.received_date DESC, se.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Stock Entry History</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="content">
    <h1>Stock Entry History</h1>
    <a href="index.php" class="btn">⬅ Back to Stock Entry</a>

    <table border="1" cellpadding="8" style="margin-top:12px; width:100%;">
        <tr>
            <th>ID</th>
            <th>PO No</th>
            <th>Part No</th>
            <th>Part Name</th>
            <th>Received Qty</th>
            <th>Invoice No</th>
            <th>Remarks</th>
            <th>Status</th>
            <th>Date</th>
        </tr>
        <?php foreach ($entries as $e): ?>
        <tr>
            <td><?= htmlspecialchars($e['id']) ?></td>
            <td><?= htmlspecialchars($e['po_no']) ?></td>
            <td><?= htmlspecialchars($e['part_no']) ?></td>
            <td><?= htmlspecialchars($e['part_name']) ?></td>
            <td><?= htmlspecialchars($e['received_qty']) ?></td>
            <td><?= htmlspecialchars($e['invoice_no'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($e['remarks'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($e['status']) ?></td>
            <td><?= isset($e['received_date']) ? htmlspecialchars($e['received_date']) : '' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

</body>
</html>
