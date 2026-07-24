<?php
session_start();
include 'db_connect.php';

// Check if the receptionist is logged in
if (!isset($_SESSION['receptionist_id'])) {
    header("Location: login.php");
    exit();
}

// Get the logged-in receptionist's name
$receptionist_name = isset($_SESSION['receptionist_name']) ? $_SESSION['receptionist_name'] : 'Admin';

// Handle export to CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="patient_records.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Patient ID', 'Patient Name', 'Visited Date', 'Hospital Name', 'Doctor ID', 'Doctor Consulted', 'Reason for Visit', 'Diagnosis', 'Lab Tests', 'Test Results', 'Medications Prescribed', 'Using or Not', 'Tags']);

    $query = "SELECT * FROM patient_records";
    $conditions = [];
    $params = [];

    if (isset($_GET['patient_id']) && !empty(trim($_GET['patient_id']))) {
        $patient_id = trim($_GET['patient_id']);
        $conditions[] = "patient_id = :patient_id";
        $params[':patient_id'] = $patient_id;
    }
    if (isset($_GET['reason_for_visit']) && !empty($_GET['reason_for_visit'])) {
        $conditions[] = "reason_for_visit = :reason_for_visit";
        $params[':reason_for_visit'] = $_GET['reason_for_visit'];
    }
    if (isset($_GET['using_or_not']) && !empty($_GET['using_or_not'])) {
        $conditions[] = "using_or_not = :using_or_not";
        $params[':using_or_not'] = $_GET['using_or_not'];
    }
    if (isset($_GET['visited_date']) && !empty($_GET['visited_date'])) {
        $conditions[] = "checkup_date = :visited_date";
        $params[':visited_date'] = $_GET['visited_date'];
    }
    if (isset($_GET['tags']) && !empty($_GET['tags'])) {
        $conditions[] = "tags LIKE :tags";
        $params[':tags'] = '%' . trim($_GET['tags']) . '%';
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        fputcsv($output, [
            $row['patient_id'],
            $row['name'],
            $row['checkup_date'],
            $row['hospital_name'],
            $row['doctor_id'],
            $row['doctor_consulted'],
            $row['reason_for_visit'],
            $row['diagnosis'],
            $row['lab_tests'],
            $row['test_results'],
            $row['medications_prescribed'],
            $row['using_or_not'],
            $row['tags']
        ]);
    }
    fclose($output);
    exit();
}

// Function to generate next patient ID
function generatePatientId($conn) {
    $stmt = $conn->prepare("SELECT MAX(patient_id) as max_id FROM patient_records");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $max_id = $result['max_id'];

    if ($max_id) {
        $num = intval(substr($max_id, 1)) + 1;
    } else {
        $num = 1;
    }
    return 'P' . str_pad($num, 3, '0', STR_PAD_LEFT);
}

// Handle adding new patient record
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_patient'])) {
    $patient_id = trim($_POST['patient_id']);
    $name = trim($_POST['name']);
    $checkup_date = $_POST['checkup_date'];
    $hospital_name = $_POST['hospital_name'];
    $doctor_id = $_POST['doctor_id'];
    $doctor_consulted = $_POST['doctor_consulted'];
    $reason_for_visit = $_POST['reason_for_visit'];
    $diagnosis = $_POST['diagnosis'];
    $lab_tests = $_POST['lab_tests'];
    $test_results = $_POST['test_results'];
    $medications_prescribed = $_POST['medications_prescribed'];
    $using_or_not = $_POST['using_or_not'];
    $tags = trim($_POST['tags']);

    if (!isset($_GET['patient_id']) || empty(trim($_GET['patient_id']))) {
        $check_stmt = $conn->prepare("SELECT name FROM patient_records WHERE name = :name LIMIT 1");
        $check_stmt->bindParam(':name', $name);
        $check_stmt->execute();
        $existing_name = $check_stmt->fetchColumn();

        if ($existing_name) {
            $message = "Error: Name '$name' already exists, please search the patient first.";
        } else {
            $patient_id = generatePatientId($conn);
            $stmt = $conn->prepare("INSERT INTO patient_records (patient_id, name, checkup_date, hospital_name, doctor_id, doctor_consulted, reason_for_visit, diagnosis, lab_tests, test_results, medications_prescribed, using_or_not, tags) VALUES (:patient_id, :name, :checkup_date, :hospital_name, :doctor_id, :doctor_consulted, :reason_for_visit, :diagnosis, :lab_tests, :test_results, :medications_prescribed, :using_or_not, :tags)");
            $stmt->bindParam(':patient_id', $patient_id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':checkup_date', $checkup_date);
            $stmt->bindParam(':hospital_name', $hospital_name);
            $stmt->bindParam(':doctor_id', $doctor_id);
            $stmt->bindParam(':doctor_consulted', $doctor_consulted);
            $stmt->bindParam(':reason_for_visit', $reason_for_visit);
            $stmt->bindParam(':diagnosis', $diagnosis);
            $stmt->bindParam(':lab_tests', $lab_tests);
            $stmt->bindParam(':test_results', $test_results);
            $stmt->bindParam(':medications_prescribed', $medications_prescribed);
            $stmt->bindParam(':using_or_not', $using_or_not);
            $stmt->bindParam(':tags', $tags);
            if ($stmt->execute()) {
                $message = "New patient with ID '$patient_id' and name '$name' added successfully!";
            } else {
                $message = "Error adding new patient.";
            }
        }
    } else {
        $searched_patient_id = trim($_GET['patient_id']);
        if ($patient_id !== $searched_patient_id) {
            $message = "Error: Patient ID cannot be changed after search. Use the searched ID '$searched_patient_id'.";
        } else {
            $stmt = $conn->prepare("INSERT INTO patient_records (patient_id, name, checkup_date, hospital_name, doctor_id, doctor_consulted, reason_for_visit, diagnosis, lab_tests, test_results, medications_prescribed, using_or_not, tags) VALUES (:patient_id, :name, :checkup_date, :hospital_name, :doctor_id, :doctor_consulted, :reason_for_visit, :diagnosis, :lab_tests, :test_results, :medications_prescribed, :using_or_not, :tags)");
            $stmt->bindParam(':patient_id', $patient_id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':checkup_date', $checkup_date);
            $stmt->bindParam(':hospital_name', $hospital_name);
            $stmt->bindParam(':doctor_id', $doctor_id);
            $stmt->bindParam(':doctor_consulted', $doctor_consulted);
            $stmt->bindParam(':reason_for_visit', $reason_for_visit);
            $stmt->bindParam(':diagnosis', $diagnosis);
            $stmt->bindParam(':lab_tests', $lab_tests);
            $stmt->bindParam(':test_results', $test_results);
            $stmt->bindParam(':medications_prescribed', $medications_prescribed);
            $stmt->bindParam(':using_or_not', $using_or_not);
            $stmt->bindParam(':tags', $tags);
            if ($stmt->execute()) {
                $message = "Patient history added successfully for ID '$patient_id'!";
            } else {
                $message = "Error adding patient history.";
            }
        }
    }
}

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_patient'])) {
    $patient_id = trim($_POST['patient_id']);
    $name = trim($_POST['name']);
    $checkup_date = $_POST['checkup_date'];
    $hospital_name = $_POST['hospital_name'];
    $doctor_id = $_POST['doctor_id'];
    $doctor_consulted = $_POST['doctor_consulted'];
    $reason_for_visit = $_POST['reason_for_visit'];
    $diagnosis = $_POST['diagnosis'];
    $lab_tests = $_POST['lab_tests'];
    $test_results = $_POST['test_results'];
    $medications_prescribed = $_POST['medications_prescribed'];
    $using_or_not = $_POST['using_or_not'];
    $tags = trim($_POST['tags']);
    $record_id = $_POST['record_id'];

    $stmt = $conn->prepare("UPDATE patient_records SET name = :name, checkup_date = :checkup_date, hospital_name = :hospital_name, doctor_id = :doctor_id, doctor_consulted = :doctor_consulted, reason_for_visit = :reason_for_visit, diagnosis = :diagnosis, lab_tests = :lab_tests, test_results = :test_results, medications_prescribed = :medications_prescribed, using_or_not = :using_or_not, tags = :tags WHERE id = :record_id");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':checkup_date', $checkup_date);
    $stmt->bindParam(':hospital_name', $hospital_name);
    $stmt->bindParam(':doctor_id', $doctor_id);
    $stmt->bindParam(':doctor_consulted', $doctor_consulted);
    $stmt->bindParam(':reason_for_visit', $reason_for_visit);
    $stmt->bindParam(':diagnosis', $diagnosis);
    $stmt->bindParam(':lab_tests', $lab_tests);
    $stmt->bindParam(':test_results', $test_results);
    $stmt->bindParam(':medications_prescribed', $medications_prescribed);
    $stmt->bindParam(':using_or_not', $using_or_not);
    $stmt->bindParam(':tags', $tags);
    $stmt->bindParam(':record_id', $record_id);

    if ($stmt->execute()) {
        $message = "Record updated successfully for ID '$patient_id'!";
    } else {
        $message = "Error updating record.";
    }
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $record_id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM patient_records WHERE id = :id");
    $stmt->bindParam(':id', $record_id);
    if ($stmt->execute()) {
        $message = "Record deleted successfully!";
    } else {
        $message = "Error deleting record.";
    }
}

// Prepopulate patient_id and name if searching for a patient
$prepopulate_patient_id = isset($_GET['patient_id']) && !empty(trim($_GET['patient_id'])) ? trim($_GET['patient_id']) : '';
$prepopulate_name = '';
if ($prepopulate_patient_id) {
    $name_stmt = $conn->prepare("SELECT name FROM patient_records WHERE patient_id = :patient_id LIMIT 1");
    $name_stmt->bindParam(':patient_id', $prepopulate_patient_id);
    $name_stmt->execute();
    $name_result = $name_stmt->fetch(PDO::FETCH_ASSOC);
    if ($name_result) {
        $prepopulate_name = $name_result['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <title>Patient History Management System</title>
    <style>
        @media print {
            .dashboard-container header, .search-section, .results-actions, .pagination {
                display: none;
            }
            .results-section {
                box-shadow: none;
                padding: 0;
            }
            details {
                display: block;
            }
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            width: 60%;
            max-height: 80vh;
            overflow-y: auto;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
        }

        .modal-content form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .modal-content input, .modal-content select {
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
        }

        .modal-content button {
            padding: 10px;
            background-color: #2c3e50;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: auto;
            margin-right: 10px;
        }

        .modal-content button:hover {
            background-color: #34495e;
        }

        .message {
            color: #27ae60;
            text-align: center;
            margin-bottom: 10px;
        }

        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 10px;
        }

        details {
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        summary {
            padding: 10px;
            background-color: #f9f9f9;
            cursor: pointer;
            font-weight: bold;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-buttons button {
            padding: 5px 10px;
            font-size: 12px;
        }

        .timeline-container {
            height: 200px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .summary-card {
            background-color: #f0f0f0;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .voice-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Patient History Management System</h1>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($receptionist_name); ?></span>
                <a href="logout.php" style="margin-left: 10px; color: #e74c3c; text-decoration: none;">Logout</a>
            </div>
        </header>

        <div class="search-section">
            <div class="search-box">
                <form method="GET" action="">
                    <div class="search-input">
                        <i class="fas fa-search"></i>
                        <input type="text" name="patient_id" placeholder="Search by Patient ID (e.g., P001)" value="<?php echo isset($_GET['patient_id']) ? htmlspecialchars($_GET['patient_id']) : ''; ?>">
                    </div>
                    <select name="reason_for_visit">
                        <option value="">All Reasons for Visit</option>
                        <option value="general" <?php echo (isset($_GET['reason_for_visit']) && $_GET['reason_for_visit'] == 'general') ? 'selected' : ''; ?>>General Checkup</option>
                        <option value="fever" <?php echo (isset($_GET['reason_for_visit']) && $_GET['reason_for_visit'] == 'fever') ? 'selected' : ''; ?>>Fever</option>
                        <!-- Add other options as needed -->
                    </select>
                    <select name="using_or_not">
                        <option value="">All Using Status</option>
                        <option value="yes" <?php echo (isset($_GET['using_or_not']) && $_GET['using_or_not'] == 'yes') ? 'selected' : ''; ?>>Yes</option>
                        <option value="no" <?php echo (isset($_GET['using_or_not']) && $_GET['using_or_not'] == 'no') ? 'selected' : ''; ?>>No</option>
                    </select>
                    <input type="date" name="visited_date" class="date-filter" placeholder="Visited Date" value="<?php echo isset($_GET['visited_date']) ? htmlspecialchars($_GET['visited_date']) : ''; ?>">
                    <input type="text" name="tags" placeholder="Search by Tags (e.g., Chronic)" value="<?php echo isset($_GET['tags']) ? htmlspecialchars($_GET['tags']) : ''; ?>">
                    <button type="submit" class="search-btn">Search</button>
                    <button type="button" class="clear-btn" onclick="clearSearch()">Clear Search</button>
                </form>
            </div>
        </div>

        <div class="results-section">
            <div class="results-header">
                <h2>Patient History Records</h2>
                <div class="results-actions">
                    <button class="export-btn" onclick="window.location.href='?export=1&<?php echo http_build_query($_GET); ?>'"><i class="fas fa-download"></i> Export</button>
                    <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                    <button class="add-btn" onclick="document.getElementById('addModal').style.display='block'"><i class="fas fa-plus"></i> Add</button>
                </div>
            </div>

            <div class="table-container">
                <?php
                try {
                    $query = "SELECT * FROM patient_records";
                    $conditions = [];
                    $params = [];

                    if (isset($_GET['patient_id']) && !empty(trim($_GET['patient_id']))) {
                        $patient_id = trim($_GET['patient_id']);
                        $conditions[] = "patient_id = :patient_id";
                        $params[':patient_id'] = $patient_id;
                    }
                    if (isset($_GET['reason_for_visit']) && !empty($_GET['reason_for_visit'])) {
                        $conditions[] = "reason_for_visit = :reason_for_visit";
                        $params[':reason_for_visit'] = $_GET['reason_for_visit'];
                    }
                    if (isset($_GET['using_or_not']) && !empty($_GET['using_or_not'])) {
                        $conditions[] = "using_or_not = :using_or_not";
                        $params[':using_or_not'] = $_GET['using_or_not'];
                    }
                    if (isset($_GET['visited_date']) && !empty($_GET['visited_date'])) {
                        $conditions[] = "checkup_date = :visited_date";
                        $params[':visited_date'] = $_GET['visited_date'];
                    }
                    if (isset($_GET['tags']) && !empty($_GET['tags'])) {
                        $conditions[] = "tags LIKE :tags";
                        $params[':tags'] = '%' . trim($_GET['tags']) . '%';
                    }

                    if (!empty($conditions)) {
                        $query .= " WHERE " . implode(" AND ", $conditions);
                    }

                    $stmt = $conn->prepare($query);
                    foreach ($params as $key => $value) {
                        $stmt->bindValue($key, $value);
                    }
                    $stmt->execute();
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $grouped_results = [];
                    foreach ($results as $row) {
                        $key = $row['patient_id'] . '|' . $row['name'];
                        if (!isset($grouped_results[$key])) {
                            $grouped_results[$key] = [];
                        }
                        $grouped_results[$key][] = $row;
                    }

                    if (count($results) > 0) {
                        foreach ($grouped_results as $key => $group) {
                            $patient_info = explode('|', $key);
                            $patient_id = $patient_info[0];
                            $name = $patient_info[1];
                            $visit_count = count($group);
                            $latest_diagnosis = $group[count($group) - 1]['diagnosis'] ?? 'N/A';
                            echo "<div class='summary-card'>";
                            echo "<h3>Summary for $name (ID: $patient_id)</h3>";
                            echo "<p>Total Visits: $visit_count | Latest Diagnosis: $latest_diagnosis</p>";
                            echo "</div>";
                            echo "<details>";
                            echo "<summary>Patient ID: $patient_id - Name: $name</summary>";
                            echo "<div class='timeline-container' id='timeline-$patient_id'></div>";
                            echo "<table>";
                            echo "<thead><tr><th>Visited Date</th><th>Hospital Name</th><th>Doctor ID</th><th>Doctor Consulted</th><th>Reason for Visit</th><th>Diagnosis</th><th>Lab Tests</th><th>Test Results</th><th>Medications Prescribed</th><th>Using or Not</th><th>Tags</th><th>Actions</th></tr></thead>";
                            echo "<tbody>";
                            $timeline_items = [];
                            $item_id = 0;
                            foreach ($group as $row) {
                                $status_class = isset($row['using_or_not']) && strtolower($row['using_or_not']) == 'yes' ? 'normal' : 'abnormal';
                                echo "<tr>";
                                echo "<td>" . (isset($row['checkup_date']) ? htmlspecialchars($row['checkup_date']) : '') . "</td>";
                                echo "<td>" . (isset($row['hospital_name']) ? htmlspecialchars($row['hospital_name']) : '') . "</td>";
                                echo "<td>" . (isset($row['doctor_id']) ? htmlspecialchars($row['doctor_id']) : '') . "</td>";
                                echo "<td>" . (isset($row['doctor_consulted']) ? htmlspecialchars($row['doctor_consulted']) : '') . "</td>";
                                echo "<td>" . (isset($row['reason_for_visit']) ? htmlspecialchars($row['reason_for_visit']) : '') . "</td>";
                                echo "<td>" . (isset($row['diagnosis']) ? htmlspecialchars($row['diagnosis']) : '') . "</td>";
                                echo "<td>" . (isset($row['lab_tests']) ? htmlspecialchars($row['lab_tests']) : '') . "</td>";
                                echo "<td>" . (isset($row['test_results']) ? htmlspecialchars($row['test_results']) : '') . "</td>";
                                echo "<td>" . (isset($row['medications_prescribed']) ? htmlspecialchars($row['medications_prescribed']) : '') . "</td>";
                                echo "<td><span class='status " . $status_class . "'>" . (isset($row['using_or_not']) ? htmlspecialchars(ucfirst(strtolower($row['using_or_not']))) : '') . "</span></td>";
                                echo "<td>" . (isset($row['tags']) ? htmlspecialchars($row['tags']) : '') . "</td>";
                                echo "<td class='action-buttons'>";
                                echo "<button class='edit-btn' onclick=\"openEditModal('" . $row['id'] . "', '" . htmlspecialchars($row['patient_id']) . "', '" . htmlspecialchars($row['name']) . "', '" . htmlspecialchars($row['checkup_date']) . "', '" . htmlspecialchars($row['hospital_name']) . "', '" . htmlspecialchars($row['doctor_id']) . "', '" . htmlspecialchars($row['doctor_consulted']) . "', '" . htmlspecialchars($row['reason_for_visit']) . "', '" . htmlspecialchars($row['diagnosis']) . "', '" . htmlspecialchars($row['lab_tests']) . "', '" . htmlspecialchars($row['test_results']) . "', '" . htmlspecialchars($row['medications_prescribed']) . "', '" . htmlspecialchars($row['using_or_not']) . "', '" . htmlspecialchars($row['tags']) . "')\"><i class='fas fa-edit'></i> Edit</button>";
                                echo "<button class='delete-btn' onclick=\"confirmDelete('" . $row['id'] . "')\"><i class='fas fa-trash'></i> Delete</button>";
                                echo "</td>";
                                echo "</tr>";
                                $timeline_items[] = ['id' => $item_id++, 'content' => $row['reason_for_visit'], 'start' => $row['checkup_date']];
                            }
                            echo "</tbody>";
                            echo "</table>";
                            echo "</details>";
                            echo "<script>
                                const container = document.getElementById('timeline-$patient_id');
                                const items = new vis.DataSet(" . json_encode($timeline_items) . ");
                                const options = {};
                                new vis.Timeline(container, items, options);
                            </script>";
                        }
                    } else {
                        echo "<p class='no-records'>No records found for the selected criteria.</p>";
                    }
                } catch (PDOException $e) {
                    echo "<p class='no-records'>Error: Table 'patient_records' does not exist or query failed. Please recreate the table or check the database.</p>";
                }
                ?>
            </div>

            <div class="pagination">
                <button class="page-btn"><i class="fas fa-chevron-left"></i></button>
                <button class="page-btn active">1</button>
                <button class="page-btn">2</button>
                <button class="page-btn">3</button>
                <button class="page-btn"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>

        <!-- Add Modal -->
        <div id="addModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('addModal').style.display='none'">×</span>
                <?php if (!empty($message)): ?>
                    <div class="<?php echo strpos($message, 'Error') === 0 ? 'error' : 'message'; ?>"><?php echo $message; ?></div>
                <?php endif; ?>
                <form method="POST" action="" onsubmit="syncOfflineData(event)">
                    <div class="voice-input-group">
                        <input type="text" name="patient_id" id="add_patient_id" placeholder="Patient ID (e.g., P001)" value="<?php echo htmlspecialchars($prepopulate_patient_id); ?>" <?php echo ($prepopulate_patient_id || (!isset($_GET['patient_id']) || empty(trim($_GET['patient_id'])))) ? 'readonly' : ''; ?> required>
                        <button type="button" onclick="startDictation('add_patient_id')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="name" id="add_name" placeholder="Patient Name" value="<?php echo htmlspecialchars($prepopulate_name); ?>" <?php echo $prepopulate_patient_id ? 'readonly' : ''; ?> required>
                        <button type="button" onclick="startDictation('add_name')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="date" name="checkup_date" id="add_checkup_date" placeholder="Visited Date" required>
                        <button type="button" onclick="startDictation('add_checkup_date')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="hospital_name" id="add_hospital_name" placeholder="Hospital Name" required>
                        <button type="button" onclick="startDictation('add_hospital_name')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="doctor_id" id="add_doctor_id" placeholder="Doctor ID (e.g., D101)" required>
                        <button type="button" onclick="startDictation('add_doctor_id')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="doctor_consulted" id="add_doctor_consulted" placeholder="Doctor Consulted" required>
                        <button type="button" onclick="startDictation('add_doctor_consulted')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="reason_for_visit" id="add_reason_for_visit" placeholder="Reason for Visit" required>
                        <button type="button" onclick="startDictation('add_reason_for_visit')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="diagnosis" id="add_diagnosis" placeholder="Diagnosis">
                        <button type="button" onclick="startDictation('add_diagnosis')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="lab_tests" id="add_lab_tests" placeholder="Lab Tests">
                        <button type="button" onclick="startDictation('add_lab_tests')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="test_results" id="add_test_results" placeholder="Test Results">
                        <button type="button" onclick="startDictation('add_test_results')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="medications_prescribed" id="add_medications_prescribed" placeholder="Medications Prescribed" required>
                        <button type="button" onclick="startDictation('add_medications_prescribed')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <select name="using_or_not" id="add_using_or_not" required>
                            <option value="">Select Using Status</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                        <button type="button" onclick="startDictation('add_using_or_not')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="tags" id="add_tags" placeholder="Tags (e.g., Chronic, Urgent)" value="">
                        <button type="button" onclick="startDictation('add_tags')" style="padding: 5px;">🎙️</button>
                    </div>
                    <button type="submit" name="add_patient">Add Patient</button>
                    <button type="button" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('editModal').style.display='none'">×</span>
                <form method="POST" action="" onsubmit="syncOfflineData(event)">
                    <input type="hidden" name="record_id" id="edit_record_id">
                    <div class="voice-input-group">
                        <input type="text" name="patient_id" id="edit_patient_id" placeholder="Patient ID (e.g., P001)" readonly required>
                        <button type="button" onclick="startDictation('edit_patient_id')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="name" id="edit_name" placeholder="Patient Name" readonly required>
                        <button type="button" onclick="startDictation('edit_name')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="date" name="checkup_date" id="edit_checkup_date" placeholder="Visited Date" required>
                        <button type="button" onclick="startDictation('edit_checkup_date')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="hospital_name" id="edit_hospital_name" placeholder="Hospital Name" required>
                        <button type="button" onclick="startDictation('edit_hospital_name')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="doctor_id" id="edit_doctor_id" placeholder="Doctor ID (e.g., D101)" required>
                        <button type="button" onclick="startDictation('edit_doctor_id')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="doctor_consulted" id="edit_doctor_consulted" placeholder="Doctor Consulted" required>
                        <button type="button" onclick="startDictation('edit_doctor_consulted')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="reason_for_visit" id="edit_reason_for_visit" placeholder="Reason for Visit" required>
                        <button type="button" onclick="startDictation('edit_reason_for_visit')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="diagnosis" id="edit_diagnosis" placeholder="Diagnosis">
                        <button type="button" onclick="startDictation('edit_diagnosis')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="lab_tests" id="edit_lab_tests" placeholder="Lab Tests">
                        <button type="button" onclick="startDictation('edit_lab_tests')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="test_results" id="edit_test_results" placeholder="Test Results">
                        <button type="button" onclick="startDictation('edit_test_results')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="medications_prescribed" id="edit_medications_prescribed" placeholder="Medications Prescribed" required>
                        <button type="button" onclick="startDictation('edit_medications_prescribed')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <select name="using_or_not" id="edit_using_or_not" required>
                            <option value="">Select Using Status</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                        <button type="button" onclick="startDictation('edit_using_or_not')" style="padding: 5px;">🎙️</button>
                    </div>
                    <div class="voice-input-group">
                        <input type="text" name="tags" id="edit_tags" placeholder="Tags (e.g., Chronic, Urgent)" value="">
                        <button type="button" onclick="startDictation('edit_tags')" style="padding: 5px;">🎙️</button>
                    </div>
                    <button type="submit" name="edit_patient">Save Changes</button>
                    <button type="button" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                </form>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/vis-timeline/7.4.9/vis-timeline-graph2d.min.js"></script>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/vis-timeline/7.4.9/vis-timeline-graph2d.min.css" rel="stylesheet">

        <script>
            // Close modal when clicking outside
            window.onclick = function(event) {
                var addModal = document.getElementById('addModal');
                var editModal = document.getElementById('editModal');
                if (event.target == addModal) addModal.style.display = "none";
                if (event.target == editModal) editModal.style.display = "none";
            }

            // Clear search filters
            function clearSearch() {
                document.querySelector('input[name="patient_id"]').value = '';
                document.querySelector('select[name="reason_for_visit"]').value = '';
                document.querySelector('select[name="using_or_not"]').value = '';
                document.querySelector('input[name="visited_date"]').value = '';
                document.querySelector('input[name="tags"]').value = '';
                document.querySelector('form').submit();
            }

            // Open edit modal with pre-filled data
            function openEditModal(id, patient_id, name, checkup_date, hospital_name, doctor_id, doctor_consulted, reason_for_visit, diagnosis, lab_tests, test_results, medications_prescribed, using_or_not, tags) {
                document.getElementById('edit_record_id').value = id;
                document.getElementById('edit_patient_id').value = patient_id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_checkup_date').value = checkup_date;
                document.getElementById('edit_hospital_name').value = hospital_name;
                document.getElementById('edit_doctor_id').value = doctor_id;
                document.getElementById('edit_doctor_consulted').value = doctor_consulted;
                document.getElementById('edit_reason_for_visit').value = reason_for_visit;
                document.getElementById('edit_diagnosis').value = diagnosis;
                document.getElementById('edit_lab_tests').value = lab_tests;
                document.getElementById('edit_test_results').value = test_results;
                document.getElementById('edit_medications_prescribed').value = medications_prescribed;
                document.getElementById('edit_using_or_not').value = using_or_not;
                document.getElementById('edit_tags').value = tags;
                document.getElementById('editModal').style.display = 'block';
            }

            // Confirm delete
            function confirmDelete(id) {
                if (confirm("Are you sure you want to delete this record?")) {
                    window.location.href = '?delete=1&id=' + id;
                }
            }

            // Voice input
            let recognition;
            function startDictation(fieldId) {
                if (recognition) recognition.stop(); // Stop any existing recognition
                recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
                recognition.lang = 'en-US'; // Set language (adjust as needed)
                recognition.interimResults = false;
                recognition.maxAlternatives = 1;

                recognition.onresult = (event) => {
                    const transcript = event.results[0][0].transcript.trim().toLowerCase();
                    const field = document.getElementById(fieldId);
                    if (field.tagName.toLowerCase() === 'select') {
                        // Handle select element (e.g., using_or_not)
                        const options = field.options;
                        for (let i = 0; i < options.length; i++) {
                            if (options[i].value.toLowerCase() === transcript || options[i].text.toLowerCase() === transcript) {
                                field.value = options[i].value;
                                break;
                            }
                        }
                    } else if (field.type === 'date') {
                        // Attempt to parse date from voice (basic format, e.g., "April 11 2025" or "2025-04-11")
                        const dateMatch = transcript.match(/(\d{4})-(\d{2})-(\d{2})|(\w+)\s+(\d{1,2})\s+(\d{4})/);
                        if (dateMatch) {
                            let dateStr = transcript;
                            if (dateMatch[1]) {
                                dateStr = dateMatch[0]; // Use YYYY-MM-DD format if detected
                            } else if (dateMatch[4]) {
                                const monthNames = ["january", "february", "march", "april", "may", "june", "july", "august", "september", "october", "november", "december"];
                                const month = monthNames.indexOf(dateMatch[4].toLowerCase()) + 1;
                                const day = parseInt(dateMatch[5], 10);
                                const year = parseInt(dateMatch[6], 10);
                                dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                            }
                            field.value = dateStr;
                        } else {
                            field.value = transcript; // Fallback to raw transcript
                        }
                    } else {
                        field.value = transcript; // Set text input value
                    }
                    console.log(`Recognized for ${fieldId}: ${transcript}`);
                };

                recognition.onerror = (event) => {
                    console.error('Speech recognition error:', event.error);
                    alert('Error with speech recognition. Please try again or check microphone permissions.');
                };

                recognition.onend = () => {
                    console.log('Speech recognition ended for ' + fieldId);
                };

                recognition.start();
                console.log('Speech recognition started for ' + fieldId);
            }

            function stopDictation() {
                if (recognition) {
                    recognition.stop();
                    console.log('Speech recognition stopped.');
                }
            }

            // Offline data sync
            function syncOfflineData(event) {
                if (!navigator.onLine) {
                    event.preventDefault();
                    const formData = new FormData(event.target);
                    const data = Object.fromEntries(formData);
                    localStorage.setItem('offlineData', JSON.stringify(data));
                    alert('Data saved offline. Will sync when online.');
                } else if (localStorage.getItem('offlineData')) {
                    fetch('sync.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: localStorage.getItem('offlineData')
                    }).then(response => response.json())
                      .then(data => {
                          if (data.success) localStorage.removeItem('offlineData');
                      })
                      .catch(error => console.error('Sync error:', error));
                }
            }

            // Sync on online event
            window.addEventListener('online', () => {
                if (localStorage.getItem('offlineData')) {
                    fetch('sync.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: localStorage.getItem('offlineData')
                    }).then(response => response.json())
                      .then(data => {
                          if (data.success) localStorage.removeItem('offlineData');
                      })
                      .catch(error => console.error('Sync error:', error));
                }
            });
        </script>
    </div>
</body>
</html>