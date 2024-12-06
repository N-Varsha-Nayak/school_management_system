<?php
$servername = "localhost";
$username = "varsha";
$password = "123"; // Set your database password
$dbname = "school_system"; // Replace with your database name

// Create a connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to fetch students based on class and section
function fetchStudents($conn, $class, $section) {
    $sql = "
        SELECT s.id AS student_id, a.name AS student_name 
        FROM students s 
        JOIN accounts a ON s.account_id = a.id 
        WHERE s.class = ? AND s.section = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $class, $section);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];

    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    $stmt->close();
    return $students;
}

// Function to fetch distinct classes from the database
function fetchClasses($conn) {
    $sql = "SELECT DISTINCT class FROM students ORDER BY class ASC";
    $result = $conn->query($sql);
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row['class'];
    }
    return $classes;
}

// Function to fetch distinct sections based on the selected class
function fetchSections($conn, $class) {
    $sql = "SELECT DISTINCT section FROM students WHERE class = ? ORDER BY section ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $class);
    $stmt->execute();
    $result = $stmt->get_result();
    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row['section'];
    }
    $stmt->close();
    return $sections;
}

// Function to save attendance
function saveAttendance($conn, $attendanceDate, $attendanceData) {
    $attendanceMonth = date('Y-m', strtotime($attendanceDate)); // Extract the month in YYYY-MM format

    $sql = "
        INSERT INTO attendance (attendance_month, attendance_value, student_id, attendance_date, modified_date, current_session)
        VALUES (?, ?, ?, ?, NOW(), NOW()) 
        ON DUPLICATE KEY UPDATE attendance_value = VALUES(attendance_value), modified_date = NOW()";

    $stmt = $conn->prepare($sql);

    foreach ($attendanceData as $studentId => $attendanceValue) {
        $stmt->bind_param("ssis", $attendanceMonth, $attendanceValue, $studentId, $attendanceDate);

        if (!$stmt->execute()) {
            throw new Exception("Error saving attendance: " . $stmt->error);
        }
    }

    $stmt->close();
}

// Handle AJAX request to fetch students
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['class']) && isset($_POST['section'])) {
    $class = $_POST['class'];
    $section = $_POST['section'];
    $students = fetchStudents($conn, $class, $section);

    // Return student data as an HTML table
    foreach ($students as $student) {
        echo "
            <tr>
                <td>" . htmlspecialchars($student['student_name']) . "</td>
                <td><input type='radio' name='attendance[" . $student['student_id'] . "]' value='present' required></td>
                <td><input type='radio' name='attendance[" . $student['student_id'] . "]' value='absent' required></td>
            </tr>";
    }
    exit;
}

// Handle AJAX request to fetch sections based on class
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['class']) && !isset($_POST['section'])) {
    $class = $_POST['class'];
    $sections = fetchSections($conn, $class);

    // Return sections as HTML options
    foreach ($sections as $section) {
        echo "<option value='" . htmlspecialchars($section) . "'>" . htmlspecialchars($section) . "</option>";
    }
    exit;
}

// Fetch classes for dropdown
$classes = fetchClasses($conn);

// Close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance</title>
    <style>
    /* General Styles */
    body {
        font-family: 'Roboto', sans-serif;
        background-color: #f4f7fc;
        color: #333;
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    h1 {
        text-align: center;
        color: #4CAF50;
        margin-top: 40px;
        font-size: 36px;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    /* Form Styles */
    form {
        background-color: #fff;
        padding: 30px;
        margin: 40px auto;
        width: 75%;
        max-width: 800px;
        border-radius: 12px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        border: 1px solid #ddd; /* Added border */
        transition: box-shadow 0.3s ease;
    }

    form:hover {
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
    }

    label {
        display: block;
        margin-bottom: 10px;
        font-size: 16px;
        color: #333;
        font-weight: bold;
    }

    input[type="date"],
    select,
    button {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        margin-bottom: 20px;
        border-radius: 8px;
        border: 1px solid #ddd;
        background-color: #f9f9f9;
        color: #333;
        box-sizing: border-box;
        transition: all 0.3s ease;
    }

    input[type="date"]:focus,
    select:focus,
    button:focus {
        outline: none;
        border-color: #4CAF50;
        box-shadow: 0 0 10px rgba(76, 175, 80, 0.4);
    }

    select {
        background-color: #fff;
    }

    button {
        background-color: #4CAF50;
        color: white;
        font-weight: bold;
        text-transform: uppercase;
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.2s ease;
        border: 1px solid #4CAF50; /* Border for the button */
    }

    button:hover {
        background-color: #45a049;
        transform: scale(1.05);
    }

    button:active {
        background-color: #388e3c;
        transform: scale(1);
    }

    /* Table Styles */
    table {
        width: 90%;
        margin: 40px auto;
        border-collapse: collapse;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border: 1px solid #ddd; /* Border around table */
    }

    thead {
        background-color: #4CAF50;
        color: white;
        border-bottom: 2px solid #ddd; /* Border for the table header */
    }

    th,
    td {
        padding: 16px;
        text-align: center;
        font-size: 16px;
        border: 1px solid #ddd;
    }

    th {
        font-weight: bold;
    }

    tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    tbody tr:hover {
        background-color: #f1f1f1;
        cursor: pointer;
    }

    input[type="radio"] {
        transform: scale(1.4);
        cursor: pointer;
    }

    /* Alert Styles */
    .alert {
        background-color: #4CAF50;
        color: white;
        padding: 10px;
        margin: 20px auto;
        width: 80%;
        max-width: 600px;
        border-radius: 5px;
        text-align: center;
        font-size: 16px;
    }

    /* Responsive Styles */
    @media (max-width: 768px) {
        form {
            width: 90%;
        }

        table {
            width: 100%;
        }

        th,
        td {
            padding: 12px;
            font-size: 14px;
        }

        button {
            font-size: 14px;
            padding: 12px;
        }

        input[type="date"],
        select {
            font-size: 14px;
            padding: 10px;
        }
    }
</style>

</head>
<body>
    <h1>Mark Attendance</h1>

    <!-- Form to Select Class, Section, and Date -->
    <form method="POST" action="save_attendance.php" id="attendanceForm">

        <!-- Date Input (User can select past date) -->
        <label for="attendanceDate">Date:</label>
        <input type="date" name="attendance_date" id="attendanceDate" value="<?php echo date('Y-m-d'); ?>" required>

        <!-- Class Selection -->
        <label for="class">Class:</label>
        <select name="class" id="class" required>
            <option value="">Select Class</option>
            <?php foreach ($classes as $class): ?>
                <option value="<?php echo htmlspecialchars($class); ?>"><?php echo htmlspecialchars($class); ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Section Selection -->
        <label for="section">Section:</label>
        <select name="section" id="section" required>
            <option value="">Select Section</option>
        </select>

        <button type="button" id="fetchStudentsBtn">Fetch Students</button>

        <!-- Table to Display Students and Attendance Radio Buttons -->
        <table id="studentsTable">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Present</th>
                    <th>Absent</th>
                </tr>
            </thead>
            <tbody>
                <!-- Dynamic content will be loaded via JavaScript -->
            </tbody>
        </table>

        <button type="submit" name="saveAttendance">Save Attendance</button>
    </form>

    <script>
        // Fetch sections based on the selected class
        document.getElementById('class').addEventListener('change', function () {
            var className = this.value;

            if (className) {
                // AJAX request to fetch sections based on the selected class
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        document.getElementById('section').innerHTML = "<option value=''>Select Section</option>" + xhr.responseText;
                    }
                };
                xhr.send('class=' + encodeURIComponent(className));
            } else {
                // Reset section dropdown if no class is selected
                document.getElementById('section').innerHTML = "<option value=''>Select Section</option>";
            }
        });

        // Fetch students based on class and section selection
        document.getElementById('fetchStudentsBtn').addEventListener('click', function () {
            var className = document.getElementById('class').value;
            var section = document.getElementById('section').value;
            var attendanceDate = document.getElementById('attendanceDate').value;

            if (className && section && attendanceDate) {
                // AJAX request to fetch students based on the selected class, section, and date
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        document.querySelector('#studentsTable tbody').innerHTML = xhr.responseText;
                    }
                };
                xhr.send('class=' + encodeURIComponent(className) + '&section=' + encodeURIComponent(section));
            } else {
                alert('Please select Class, Section, and Date!');
            }
        });
    </script>
</body>
</html>
<?php include('footer.php') ?>