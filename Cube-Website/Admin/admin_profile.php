<?php
require_once 'dataconnection.php';

$messages = [];
$staff_id = isset($_SESSION['Staff_ID']) ? intval($_SESSION['Staff_ID']) : 0;
$staff_data = null;

// Load or initialize the JSON cache file
$cache_file = 'staff_image_cache.json';
$cache_data = [];
if (file_exists($cache_file)) {
    $json_content = file_get_contents($cache_file);
    $cache_data = json_decode($json_content, true) ?: [];
}

// Fetch staff data for the logged-in user
if ($staff_id > 0) {
    $sql = "SELECT Staff_ID, Staff_Name, Staff_Email, Staff_Role, Staff_Password, Profile_Image FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $staff_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Update cache with current database image if not set
    if ($staff_data && !isset($cache_data[$staff_id])) {
        $cache_data[$staff_id] = $staff_data['Profile_Image'] ?: 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png';
        file_put_contents($cache_file, json_encode($cache_data));
    }
}

// Handle profile image upload and update
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/';
    $image_name = $staff_data['Staff_Name'] . '_profile.png';
    $upload_file = $upload_dir . $image_name;
    $image_tmp = $_FILES['profile_image']['tmp_name'];
    $image_size = getimagesize($image_tmp);

    if ($image_size && move_uploaded_file($image_tmp, $upload_file)) {
        $update_sql = "UPDATE Staff SET Profile_Image = ? WHERE Staff_ID = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $upload_file, $staff_id);
        $success = mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);

        header('Content-Type: application/json');
        if ($success) {
            $cache_data[$staff_id] = $upload_file;
            file_put_contents($cache_file, json_encode($cache_data));
            echo json_encode(['status' => 'success', 'new_image_path' => $upload_file]);
        } else {
            unlink($upload_file); // Clean up on failure
            echo json_encode(['status' => 'error', 'message' => 'Failed to update profile image in database.']);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid image file or upload failed.']);
    }
    exit();
}

// Handle image removal
if (isset($_POST['remove_image'])) {
    if ($staff_data['Profile_Image'] && file_exists($staff_data['Profile_Image'])) {
        unlink($staff_data['Profile_Image']); // Remove the image file
    }
    $update_sql = "UPDATE Staff SET Profile_Image = NULL WHERE Staff_ID = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "i", $staff_id);
    $success = mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);

    header('Content-Type: application/json');
    if ($success) {
        $cache_data[$staff_id] = 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png';
        file_put_contents($cache_file, json_encode($cache_data));
        echo json_encode(['status' => 'success', 'new_image_path' => null]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove profile image.']);
    }
    exit();
}

mysqli_close($conn);
?>

<div style="margin-top: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: stretch; max-height: calc(100vh - 60px - 40px); overflow-y: auto;">
    <div style="flex: 1; text-align: center; min-width: 150px; display: flex; flex-direction: column; justify-content: space-between; height: 100%; padding: 20px; box-sizing: border-box;">
        <h2 style="margin-bottom: 30px;">My Profile</h2>
        <?php if (!empty($messages)): ?>
            <div style="color: #ff0000; margin-bottom: 30px; text-align: center;"><?php echo $messages[0]; ?></div>
        <?php endif; ?>
        <?php if ($staff_data): ?>
            <form id="imageForm" enctype="multipart/form-data" style="display: flex; flex-direction: column; align-items: center; height: 100%; width: 100;">
                <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff_data['Staff_ID']); ?>">
                <div style="width: 100%; height: calc(70% - 40px); border: 2px solid #ccc; border-radius: 10px; overflow: hidden; display: flex; justify-content: center; align-items: center; margin-bottom: 30px;" id="imageContainer">
                    <img id="profileImage" src="<?php echo isset($cache_data[$staff_id]) ? htmlspecialchars($cache_data[$staff_id]) : ($staff_data['Profile_Image'] ? htmlspecialchars($staff_data['Profile_Image']) : 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png'); ?>" alt="Profile Image" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                </div>
                <input type="file" name="profile_image" accept="image/png, image/jpeg" id="imageUpload" style="display: block; margin: 0 auto 15px; width: 90%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;">
                <button type="button" id="removeImageBtn" style="display: block; margin: 0 auto 30px; width: 90%; padding: 10px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">Remove Image</button>
            </form>
        <?php else: ?>
            <p style="text-align: center; color: #666; margin-top: 20px;">Profile not found.</p>
        <?php endif; ?>
    </div>
    <div style="flex: 2; padding: 20px; box-sizing: border-box;">
        <?php if ($staff_data): ?>
            <form method="POST" action="" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 25px;">
                <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff_data['Staff_ID']); ?>">
                <div style="display: flex; align-items: center;">
                    <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Email</label>
                    <input type="text" value="<?php echo htmlspecialchars($staff_data['Staff_Email']); ?>" readonly style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; background-color: #f0f0f0; cursor: not-allowed; width: 100%;">
                </div>
                <div style="display: flex; align-items: center;">
                    <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($staff_data['Staff_Name']); ?>" required style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; width: 100%;">
                </div>
                <div style="display: flex; align-items: center;">
                    <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Role</label>
                    <input type="text" value="<?php echo htmlspecialchars($staff_data['Staff_Role']); ?>" readonly style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; background-color: #f0f0f0; cursor: not-allowed; width: 100%;">
                </div>
                <div style="display: flex; align-items: center;">
                    <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Password</label>
                    <input type="password" name="password" placeholder="Leave blank to keep current" style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; width: 100%;">
                </div>
                <div style="display: flex; align-items: center;">
                    <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; width: 100%;">
                </div>
                <button type="submit" name="submit_edit" style="width: 100%; padding: 15px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Save Changes</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageUpload = document.getElementById('imageUpload');
    const profileImage = document.getElementById('profileImage');
    const removeImageBtn = document.getElementById('removeImageBtn');
    const imageForm = document.getElementById('imageForm');
    const imageContainer = document.getElementById('imageContainer');
    const staffId = <?php echo $staff_id; ?>;

    // Function to load current image from cache
    function loadImageFromCache() {
        fetch('staff_image_cache.json')
            .then(response => response.json())
            .then(data => {
                const currentPath = data[staffId] || '<?php echo $staff_data['Profile_Image'] ? htmlspecialchars($staff_data['Profile_Image']) : 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png'; ?>';
                profileImage.src = currentPath;
                imageContainer.style.display = 'flex';
            })
            .catch(error => console.error('Error loading cache:', error));
    }

    // Initial load
    loadImageFromCache();

    imageUpload.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                profileImage.src = e.target.result; // Show preview immediately
                imageContainer.style.display = 'flex';
            };
            reader.readAsDataURL(file);

            const formData = new FormData(imageForm);
            formData.append('profile_image', file);
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'error') {
                    alert(data.message);
                    loadImageFromCache(); // Revert to cached or default image
                } else {
                    profileImage.src = data.new_image_path; // Update with server path
                    imageContainer.style.display = 'flex';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loadImageFromCache(); // Revert on error
            });
        }
    });

    removeImageBtn.addEventListener('click', function() {
        if (confirm('Are you sure you want to remove the image?')) {
            profileImage.src = 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png'; // Show default immediately
            imageContainer.style.display = 'flex';
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'remove_image=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'error') {
                    alert(data.message);
                    loadImageFromCache(); // Revert to cached or default image
                } else {
                    profileImage.src = data.new_image_path || 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png';
                    imageContainer.style.display = 'flex';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loadImageFromCache(); // Revert on error
            });
        }
    });
});
</script>

<style>
    body {
        margin: 0;
        padding: 0;
        margin-top: 0px; /* Start from bottom of header (60px height) */
        margin-bottom: 0px; /* Remove bottom margin to extend to footer */
        height: calc(100vh - 60px); /* Extend to top of footer, header (60px) subtracted */
        box-sizing: border-box; /* Ensure padding/margins are included in height */
    }
    #imageContainer {
        min-height: 100px; /* Ensure minimum height to prevent collapse */
    }
</style>