<?php
require_once __DIR__ . '/../config/app.php';
require_login();
verify_csrf();

$action = $_POST['action'] ?? '';
$scope  = scope_kandang('k');

try {
    switch ($action) {
        case 'resolve':
            $id = (int) ($_POST['id'] ?? 0);

            $stmt = $conn->prepare(
                "SELECT al.id
                 FROM alerts al
                 INNER JOIN animals a ON a.id = al.animal_id
                 INNER JOIN kandangs k ON k.id = a.kandang_id
                 WHERE al.id = ? AND $scope
                 LIMIT 1"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $alert = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$alert) {
                throw new RuntimeException('Peringatan tidak ditemukan.');
            }

            $conn->query(
                'UPDATE alerts SET is_resolved = 1, is_read = 1, resolved_at = NOW() WHERE id = ' . (int) $id
            );

            flash('success', 'Peringatan ditandai selesai.');
            redirect('peringatan.php');

        case 'resolve_all':
            $conn->query(
                "UPDATE alerts al
                 INNER JOIN animals a ON a.id = al.animal_id
                 INNER JOIN kandangs k ON k.id = a.kandang_id
                 SET al.is_resolved = 1, al.is_read = 1, al.resolved_at = NOW()
                 WHERE al.is_resolved = 0 AND $scope"
            );

            flash('success', 'Semua peringatan aktif ditandai selesai.');
            redirect('peringatan.php');

        case 'read_all':
            $conn->query(
                "UPDATE alerts al
                 INNER JOIN animals a ON a.id = al.animal_id
                 INNER JOIN kandangs k ON k.id = a.kandang_id
                 SET al.is_read = 1
                 WHERE al.is_read = 0 AND $scope"
            );

            flash('success', 'Semua peringatan ditandai sudah dibaca.');
            redirect('peringatan.php');

        default:
            throw new RuntimeException('Aksi tidak dikenali.');
    }
} catch (RuntimeException $error) {
    flash('danger', $error->getMessage());
    redirect('peringatan.php');
} catch (Throwable $error) {
    flash('danger', 'Terjadi kesalahan: ' . $error->getMessage());
    redirect('peringatan.php');
}
