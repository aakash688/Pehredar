<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Employee ID Card</title>
    <style>
        @page { margin: 0; }
        body { font-family: 'Helvetica', sans-serif; margin: 0; padding: 0; }
        .card-container {
            width: 324px; /* 4.5 inches * 72 dpi */
            height: 204px; /* 2.83 inches * 72 dpi */
            border: 1px solid #ccc;
            border-radius: 15px;
            background-color: #f4f4f9;
            overflow: hidden;
            position: relative;
        }
        .header {
            background-color: #0d47a1;
            color: white;
            padding: 10px;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
        }
        .photo {
            position: absolute;
            top: 55px;
            left: 20px;
            width: 80px;
            height: 100px;
            border: 3px solid #0d47a1;
            border-radius: 5px;
        }
        .details {
            position: absolute;
            top: 55px;
            left: 115px;
            font-size: 11px;
        }
        .details p {
            margin: 0 0 5px 0;
        }
        .details .label {
            font-weight: bold;
            color: #333;
        }
        .details .value {
            color: #555;
        }
        .footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            background-color: #0d47a1;
            color: white;
            font-size: 9px;
            text-align: center;
            padding: 5px 0;
        }
        .company-logo {
            position: absolute;
            top: 5px;
            left: 10px;
            max-width: 50px;
            max-height: 35px;
        }
    </style>
</head>
<body>
    <div class="card-container">
        <?php if (isset($company_logo_src)): ?>
            <img src="<?= $company_logo_src ?>" class="company-logo">
        <?php endif; ?>
        <div class="header">
            <?= htmlspecialchars($company_settings['company_name'] ?? 'COMPANY') ?> ID CARD
        </div>
        
        <?php if (isset($employee['profile_photo_src']) && !empty($employee['profile_photo_src'])): ?>
            <img src="<?= $employee['profile_photo_src'] ?>" class="photo" alt="Profile Photo">
        <?php else: ?>
            <!-- Placeholder for missing profile photo -->
            <div class="photo" style="background-color: #e0e0e0; display: flex; align-items: center; justify-content: center; color: #666; font-size: 10px; text-align: center;">
                No Photo<br>Available
            </div>
        <?php endif; ?>

        <div class="details">
            <p><span class="label">ID:</span> <span class="value"><?= htmlspecialchars($employee['id']) ?></span></p>
            <p><span class="label">Name:</span> <span class="value"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['surname']) ?></span></p>
            <p><span class="label">Role:</span> <span class="value"><?= htmlspecialchars($employee['user_type']) ?></span></p>
            <p><span class="label">Phone:</span> <span class="value"><?= htmlspecialchars($employee['mobile_number']) ?></span></p>
            <p><span class="label">DOB:</span> <span class="value"><?= htmlspecialchars(date('d-m-Y', strtotime($employee['date_of_birth']))) ?></span></p>
            <p><span class="label">Joined:</span> <span class="value"><?= htmlspecialchars(date('d-m-Y', strtotime($employee['date_of_joining']))) ?></span></p>
        </div>

        <div class="footer">
            This card is the property of <?= htmlspecialchars($company_settings['company_name'] ?? 'the company') ?>. If found, please return.
        </div>
    </div>
</body>
</html> 