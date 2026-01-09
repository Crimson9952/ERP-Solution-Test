<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

// This page shows a receive form for all lines under a PO and processes the POST to receive quantities.

if (!isset($_REQUEST['po_no'])) {
    header("Location: index.php");
    exit;
}

$po_no = $_REQUEST['po_no'];

// Fetch all lines under this PO with part names and remaining qty
$linesStmt = $pdo->prepare("SELECT po.id, po.part_no, po.qty AS ordered, pm.part_name,
    COALESCE((SELECT SUM(se.received_qty) FROM stock_entries se WHERE se.po_id = po.id AND se.status='posted'),0) AS received
    FROM purchase_orders po
    JOIN part_master pm ON po.part_no = pm.part_no
    WHERE po.po_no = ?");
$linesStmt->execute([$po_no]);
$lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$lines) {
    setModal('Receive Failed', 'PO not found');
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // expected inputs: line_id[], received_qty[], invoice_no (optional)
    $lineIds = $_POST['line_id'] ?? [];
    $recvQtys = $_POST['received_qty'] ?? [];
    $invoiceNo = trim($_POST['invoice_no'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    // normalize
    if (!is_array($lineIds)) $lineIds = [$lineIds];
    if (!is_array($recvQtys)) $recvQtys = [$recvQtys];

    $pdo->beginTransaction();
    try {
        $insertStock = $pdo->prepare("INSERT INTO stock_entries (po_id, part_no, received_qty, invoice_no, status) VALUES (?, ?, ?, ?, 'posted')");
        $upInventory = $pdo->prepare("INSERT INTO inventory (part_no, qty) VALUES (?, ?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)");
        $updatePo = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");

        foreach ($lineIds as $i => $lid) {
            $lid = (int)$lid;
            $qty = isset($recvQtys[$i]) ? (float)$recvQtys[$i] : 0;
            if ($qty <= 0) continue; // skip zero entries

            // find matching line info
            $lineInfo = null;
            foreach ($lines as $ln) if ($ln['id'] == $lid) { $lineInfo = $ln; break; }
            if (!$lineInfo) throw new Exception('Invalid PO line: ' . $lid);

            $remaining = $lineInfo['ordered'] - $lineInfo['received'];
            if ($qty > $remaining) throw new Exception('Received qty for ' . $lineInfo['part_no'] . ' exceeds remaining');

            // insert stock entry
            $insertStock->execute([$lid, $lineInfo['part_no'], $qty, $invoiceNo]);

            // update inventory
            $upInventory->execute([$lineInfo['part_no'], $qty]);

            // update PO line status
            $newStatus = ($qty + $lineInfo['received']) >= $lineInfo['ordered'] ? 'closed' : 'partial';
            $updatePo->execute([$newStatus, $lid]);
        }

        $pdo->commit();
        setModal('Received', 'Stock received successfully');
        header('Location: index.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        setModal('Receive Failed', $e->getMessage());
        header('Location: receive_all.php?po_no=' . urlencode($po_no));
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Receive ALL - <?= htmlspecialchars($po_no) ?></title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="content">
<h1>Receive Stock for <?= htmlspecialchars($po_no) ?></h1>

<form method="post">
    <input type="hidden" name="po_no" value="<?= htmlspecialchars($po_no) ?>">
    <table border="1" cellpadding="8">
        <tr>
            <th>Part No</th>
            <th>Part Name</th>
            <th>Ordered</th>
            <th>Already Received</th>
            <th>Remaining</th>
            <th>Receive Qty</th>
        </tr>
        <?php foreach ($lines as $ln):
            $remaining = $ln['ordered'] - $ln['received'];
        ?>
        <tr>
            <td><?= htmlspecialchars($ln['part_no']) ?></td>
            <td><?= htmlspecialchars($ln['part_name']) ?></td>
            <td><?= htmlspecialchars($ln['ordered']) ?></td>
            <td><?= htmlspecialchars($ln['received']) ?></td>
            <td><?= htmlspecialchars($remaining) ?></td>
            <td>
                <input type="hidden" name="line_id[]" value="<?= $ln['id'] ?>">
                <input type="number" step="0.001" name="received_qty[]" min="0" max="<?= $remaining ?>" value="<?= $remaining ?>">
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <p>
        Invoice No<br>
        <input name="invoice_no">
    </p>
    <p>
        Date<br>
        <input type="date" name="received_date">
    </p>
    <p>
        Remarks<br>
        <input name="remarks">
    </p>

    <button type="button" class="btn" onclick="document.querySelectorAll('input[name=\'received_qty[]\']').forEach(i=>i.value = i.max); document.querySelector('form').submit();">Confirm</button>
    <a href="index.php" class="btn">Cancel</a>
</form>

</div>
</body>
</html>
