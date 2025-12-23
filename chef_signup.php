<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $details = mysqli_real_escape_string($conn, $_POST["details"]);
    $user_id = $_SESSION['user_id'];

    $specialties = [];
    if (isset($_POST["specialties"])) {
        foreach ($_POST["specialties"] as $specialty) {
            if ($specialty === "Other" && !empty($_POST["otherSpecialty"])) {
                $specialties[] = mysqli_real_escape_string($conn, $_POST["otherSpecialty"]);
            } elseif ($specialty !== "Other") {
                $specialties[] = mysqli_real_escape_string($conn, $specialty);
            }
        }
    }
    $specialtiesStr = implode(", ", $specialties);

    $updateChefQuery = "UPDATE chef SET 
                       specialties = '$specialtiesStr'
                       WHERE user_id = $user_id";
    
    if (mysqli_query($conn, $updateChefQuery)) {
        
        $chefResult = mysqli_query($conn, "SELECT ChefID FROM chef WHERE user_id = $user_id");
        $chefRow = mysqli_fetch_assoc($chefResult);
        $chef_id = $chefRow['ChefID'];
        
        mysqli_query($conn, "DELETE FROM graduate WHERE chef_id = $chef_id");
        
        if (isset($_POST["graduationYear"]) && isset($_POST["university"]) && isset($_POST["major"])) {
            $gradYear = mysqli_real_escape_string($conn, $_POST["graduationYear"]);
            $university = mysqli_real_escape_string($conn, $_POST["university"]);
            $major = mysqli_real_escape_string($conn, $_POST["major"]);
            
            $insertEducationQuery = "INSERT INTO graduate (year, university, major, chef_id) 
                                   VALUES ('$gradYear', '$university', '$major', $chef_id)";
            mysqli_query($conn, $insertEducationQuery);
        }
        
        mysqli_query($conn, "DELETE FROM experience WHERE chef_id = $chef_id");
        
        if (isset($_POST["experiencePlace"])) {
            foreach ($_POST["experiencePlace"] as $key => $place) {
                if (!empty($place)) {
                    $position = mysqli_real_escape_string($conn, $_POST["position"][$key]);
                    $place = mysqli_real_escape_string($conn, $place);
                    $yearStart = mysqli_real_escape_string($conn, $_POST["yearStart"][$key]);
                    $yearEnd = mysqli_real_escape_string($conn, $_POST["yearEnd"][$key]);
                    
                    $insertExperienceQuery = "INSERT INTO experience (position, place, year_start, year_stop, chef_id) 
                                            VALUES ('$position', '$place', '$yearStart', '$yearEnd', $chef_id)";
                    mysqli_query($conn, $insertExperienceQuery);
                }
            }
        }
        
        header("Location: chef_pending.php"); 
        exit();
    } else {
        $message = "<div class='alert alert-danger'>Error updating profile: " . mysqli_error($conn) . "</div>";
    }
}

$user_id = $_SESSION['user_id'];
$chefQuery = "SELECT * FROM chef WHERE user_id = $user_id";
$chefResult = mysqli_query($conn, $chefQuery);
$chef = mysqli_fetch_assoc($chefResult);

$educationQuery = "SELECT * FROM graduate WHERE chef_id = {$chef['ChefID']}";
$educationResult = mysqli_query($conn, $educationQuery);
$education = mysqli_fetch_assoc($educationResult);

$experienceQuery = "SELECT * FROM experience WHERE chef_id = {$chef['ChefID']} ORDER BY year_start DESC";
$experienceResult = mysqli_query($conn, $experienceQuery);
$experiences = [];
while ($row = mysqli_fetch_assoc($experienceResult)) {
    $experiences[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chef Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: url('61c0027b-aa3a-4142-b5b4-0dd506427f1a.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .signup-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
        }
        
        .btn-submit {
            background: linear-gradient(90deg, #ffcc00, #ff9900);
            border: none;
            padding: 10px;
            border-radius: 30px;
            font-weight: bold;
            color: #fff;
        }
        
        .btn-submit:hover {
            background: linear-gradient(90deg, #ff9900, #ffcc00);
        }
        
        .experience-container, .education-container {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .add-experience, .remove-experience {
            cursor: pointer;
        }
        
        .other-specialty-container {
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <h2 class="text-center mb-4">Update Chef Profile</h2>
        <?= $message ?>
        <form method="POST" id="chefForm">
            <div class="mb-3">
                <label class="form-label">Specialties (Select all that apply)</label>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="specialties[]" value="Italian" id="italian" <?= strpos($chef['specialties'], 'Italian') !== false ? 'checked' : '' ?>>
                            <label class="form-check-label" for="italian">Italian</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="specialties[]" value="French" id="french" <?= strpos($chef['specialties'], 'French') !== false ? 'checked' : '' ?>>
                            <label class="form-check-label" for="french">French</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="specialties[]" value="Asian" id="asian" <?= strpos($chef['specialties'], 'Asian') !== false ? 'checked' : '' ?>>
                            <label class="form-check-label" for="asian">Asian</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="specialties[]" value="Mediterranean" id="mediterranean" <?= strpos($chef['specialties'], 'Mediterranean') !== false ? 'checked' : '' ?>>
                            <label class="form-check-label" for="mediterranean">Mediterranean</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="specialties[]" value="Pastry" id="pastry" <?= strpos($chef['specialties'], 'Pastry') !== false ? 'checked' : '' ?>>
                            <label class="form-check-label" for="pastry">Pastry</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input specialty-other" type="checkbox" name="specialties[]" value="Other" id="other" <?= preg_match('/^(?!.*(Italian|French|Asian|Mediterranean|Pastry)).*$/', $chef['specialties']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="other">Other</label>
                        </div>
                    </div>
                </div>
                <div class="other-specialty-container" id="otherSpecialtyContainer" style="<?= preg_match('/^(?!.*(Italian|French|Asian|Mediterranean|Pastry)).*$/', $chef['specialties']) ? 'display:block' : 'display:none' ?>">
                    <input type="text" class="form-control mt-2" name="otherSpecialty" id="otherSpecialtyInput" placeholder="Please specify your specialty" value="<?= preg_match('/^(?!.*(Italian|French|Asian|Mediterranean|Pastry)).*$/', $chef['specialties']) ? htmlspecialchars($chef['specialties']) : '' ?>">
                </div>
            </div>
            
            <h4 class="mt-4">Professional Experience</h4>
            <div id="experienceSection">
                <?php if (!empty($experiences)): ?>
                    <?php foreach ($experiences as $exp): ?>
                        <div class="experience-container">
                            <div class="row">
                                <div class="col-md-5 mb-3">
                                    <input type="text" class="form-control" name="position[]" placeholder="Position/Role" value="<?= htmlspecialchars($exp['position']) ?>" required>
                                </div>
                                <div class="col-md-5 mb-3">
                                    <input type="text" class="form-control" name="experiencePlace[]" placeholder="Restaurant/Company" value="<?= htmlspecialchars($exp['place']) ?>" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <button type="button" class="btn btn-outline-danger w-100 remove-experience">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <input type="number" class="form-control" name="yearStart[]" placeholder="Year Started" value="<?= htmlspecialchars($exp['year_start']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <input type="number" class="form-control" name="yearEnd[]" placeholder="Year Ended" value="<?= htmlspecialchars($exp['year_stop']) ?>" required>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="experience-container">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <input type="text" class="form-control" name="position[]" placeholder="Position/Role" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <input type="text" class="form-control" name="experiencePlace[]" placeholder="Restaurant/Company" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <input type="number" class="form-control" name="yearStart[]" placeholder="Year Started" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <input type="number" class="form-control" name="yearEnd[]" placeholder="Year Ended" required>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="addExperience">
                <i class="fas fa-plus"></i> Add Another Experience
            </button>
            
            <h4>Education</h4>
            <div class="education-container">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <input type="number" class="form-control" name="graduationYear" placeholder="Graduation Year" value="<?= !empty($education) ? htmlspecialchars($education['year']) : '' ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <input type="text" class="form-control" name="university" placeholder="University" value="<?= !empty($education) ? htmlspecialchars($education['university']) : '' ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <input type="text" class="form-control" name="major" placeholder="Major" value="<?= !empty($education) ? htmlspecialchars($education['major']) : '' ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-submit px-4">
                    Update Profile <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </form>
    </div>

    <script>
    document.getElementById('addExperience').addEventListener('click', function() {
        const container = document.getElementById('experienceSection');
        const newField = document.createElement('div');
        newField.className = 'experience-container mt-3';
        newField.innerHTML = `
            <div class="row">
                <div class="col-md-5 mb-3">
                    <input type="text" class="form-control" name="position[]" placeholder="Position/Role" required>
                </div>
                <div class="col-md-5 mb-3">
                    <input type="text" class="form-control" name="experiencePlace[]" placeholder="Restaurant/Company" required>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="button" class="btn btn-outline-danger w-100 remove-experience">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <input type="number" class="form-control" name="yearStart[]" placeholder="Year Started" required>
                </div>
                <div class="col-md-6 mb-3">
                    <input type="number" class="form-control" name="yearEnd[]" placeholder="Year Ended" required>
                </div>
            </div>
        `;
        container.appendChild(newField);
        
        newField.querySelector('.remove-experience').addEventListener('click', function() {
            newField.remove();
        });
    });
    
    document.querySelector('.specialty-other').addEventListener('change', function() {
        const otherContainer = document.getElementById('otherSpecialtyContainer');
        if (this.checked) {
            otherContainer.style.display = 'block';
        } else {
            otherContainer.style.display = 'none';
            document.getElementById('otherSpecialtyInput').value = '';
        }
    });
    
    document.getElementById('chefForm').addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
        submitBtn.disabled = true;
    });
</script>
</body>
</html>