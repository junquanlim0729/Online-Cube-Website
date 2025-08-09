<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['Cust_ID'])) {
    header('Location: cust_login.php');
    exit();
}

require_once 'dataconnection.php';

$customer_id = $_SESSION['Cust_ID'];
$messages = [];

// Load configuration file
$config_file = 'customer_profile_image_config.json';
$config_data = [];
if (file_exists($config_file)) {
    $config_content = file_get_contents($config_file);
    $config_data = json_decode($config_content, true) ?: [];
}
$default_image = $config_data['default_image'] ?? 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png';
$allowed_types = $config_data['allowed_types'] ?? ['image/png', 'image/jpeg'];
// Web and filesystem directories
$upload_dir_web = rtrim($config_data['upload_dir'] ?? 'customer_uploads', '/') . '/';
$upload_dir_fs  = rtrim((strpos($upload_dir_web, '/') === 0 ? $_SERVER['DOCUMENT_ROOT'] : __DIR__) . '/' . $upload_dir_web, '/') . '/';
if (!file_exists($upload_dir_fs)) {
    if (!mkdir($upload_dir_fs, 0755, true)) {
        error_log("Failed to create upload directory: $upload_dir_fs");
        $messages[] = "Error: Could not create upload directory.";
    }
}

// helper to map web path to filesystem path
function web_to_fs($path) {
    if ($path === '' || $path === null) return '';
    if (preg_match('/^[A-Za-z]:\\\\|^\//', $path)) return $path; // already fs/absolute
    return __DIR__ . '/' . ltrim($path, '/');
}

// Fetch customer data
$sql = "SELECT Cust_ID, Cust_First_Name, Cust_Last_Name, Cust_Email, Cust_Phone, Cust_Password, Profile_Image FROM Customer WHERE Cust_ID = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$current_image_web = $customer_data['Profile_Image'] ?: '';
$current_image_src = $current_image_web !== '' ? $current_image_web : $default_image;

// Verify password (admin-like flow)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_password'])) {
    $verify_password = trim($_POST['verify_password']);
    $vp = mysqli_prepare($conn, "SELECT Cust_Password FROM Customer WHERE Cust_ID = ?");
    mysqli_stmt_bind_param($vp, "i", $customer_id);
    mysqli_stmt_execute($vp);
    $r = mysqli_stmt_get_result($vp);
    $row = mysqli_fetch_assoc($r);
    mysqli_stmt_close($vp);
    $db_pwd = $row ? $row['Cust_Password'] : '';
    echo ($db_pwd !== '' && $verify_password === $db_pwd) ? 'verification_success' : 'Current password is incorrect. Please try again.';
    exit();
}

// Change password (admin-like validation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    $cp = mysqli_prepare($conn, "SELECT Cust_Password FROM Customer WHERE Cust_ID = ?");
    mysqli_stmt_bind_param($cp, "i", $customer_id);
    mysqli_stmt_execute($cp);
    $cr = mysqli_stmt_get_result($cp);
    $crow = mysqli_fetch_assoc($cr);
    mysqli_stmt_close($cp);
    $db_password = $crow ? $crow['Cust_Password'] : '';

    if ($new_password !== $confirm_password) {
        $messages[] = "New password and confirm new password do not match.";
    } elseif ($new_password === $db_password) {
        $messages[] = "New password cannot be the same as the current password.";
    } else {
        $len = strlen($new_password);
        if ($len < 8 || $len > 20) {
            $messages[] = "Password must be 8-20 characters long.";
        } elseif (!preg_match('/[A-Z]/',$new_password) || !preg_match('/[a-z]/',$new_password) || !preg_match('/[0-9]/',$new_password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/',$new_password)) {
            $messages[] = "Password must include uppercase, lowercase, number, and special character.";
        } else {
            $upd = mysqli_prepare($conn, "UPDATE Customer SET Cust_Password = ?, Last_Password_Change = NOW() WHERE Cust_ID = ?");
            mysqli_stmt_bind_param($upd, "si", $new_password, $customer_id);
            if (mysqli_stmt_execute($upd)) {
                $messages[] = "Password changed successfully.";
            } else {
                $messages[] = "Failed to update password: ".mysqli_error($conn);
            }
            mysqli_stmt_close($upd);
        }
    }
}

// Image upload
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    try {
        $tmp = $_FILES['profile_image']['tmp_name'];
        $type = mime_content_type($tmp);
        if (!in_array($type,$allowed_types)) throw new Exception('Only PNG and JPEG files are allowed.');
        $image_name = ($customer_data['Cust_First_Name'] ?? 'user') . '_' . uniqid() . '.' . pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $dest_fs  = $upload_dir_fs  . $image_name;
        $dest_web = $upload_dir_web . $image_name;
        // remove old if exists (fs path)
        if ($current_image_web) { $old_fs = web_to_fs($current_image_web); if (file_exists($old_fs) && $current_image_web !== $default_image) @unlink($old_fs); }
        if (!move_uploaded_file($tmp,$dest_fs)) throw new Exception('Failed to upload image.');
        $upd = mysqli_prepare($conn, "UPDATE Customer SET Profile_Image = ? WHERE Cust_ID = ?");
        mysqli_stmt_bind_param($upd, "si", $dest_web, $customer_id);
        if (!mysqli_stmt_execute($upd)) throw new Exception('Database update failed: '.mysqli_error($conn));
        mysqli_stmt_close($upd);
        echo json_encode(['status'=>'success','message'=>'Profile image updated successfully!','image'=>$dest_web]);
    } catch(Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage(),'image'=>$current_image_src]); }
    exit();
}

// Image remove
if (isset($_POST['remove_image'])) {
    header('Content-Type: application/json');
    try {
        if ($current_image_web) { $old_fs = web_to_fs($current_image_web); if (file_exists($old_fs) && $current_image_web !== $default_image) @unlink($old_fs); }
        $upd = mysqli_prepare($conn, "UPDATE Customer SET Profile_Image = NULL WHERE Cust_ID = ?");
        mysqli_stmt_bind_param($upd, "i", $customer_id);
        if (!mysqli_stmt_execute($upd)) throw new Exception('Database update failed: '.mysqli_error($conn));
        mysqli_stmt_close($upd);
        echo json_encode(['status'=>'success','message'=>'Profile image removed successfully!','image'=>$default_image]);
    } catch(Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage(),'image'=>$current_image_src]); }
    exit();
}
?>

<!-- Title -->
<div style="margin-bottom: 15px;">
  <h1 style="margin:0; font-size:32px; font-weight:800; color:#1f1f1f;">Edit Profile</h1>
  <p style="margin:4px 0 0; color:#666; font-size:14px;">Manage your account details, password and profile image</p>
</div>
<div id="topMessageDiv" style="margin: 6px 0 14px 0; height: 34px; position: relative; overflow: hidden;">
  <div id="topMessageContent" style="opacity:0; transform: translateY(-14px);"></div>
</div>

<!-- Layout: Narrow left, wider right -->
<div style="display:flex; gap:12px; align-items:stretch;">
  <!-- Left: image + password (scrollable area only for password) -->
  <div style="flex:0 0 30%; display:flex; flex-direction:column; gap:12px;">
    <!-- Image card -->
    <div style="background:#f8f9fa; border:1px solid #e0e0e0; border-radius:6px; padding:12px;">
      <h3 style="margin:0 0 10px 0; font-size:16px; color:#333;">Profile Image</h3>
      <div style="text-align:center; margin-bottom:12px;">
        <img id="profile-image" src="<?php echo htmlspecialchars($current_image_src); ?>" alt="Profile Image" style="width:120px; height:120px; border-radius:50%; object-fit:cover; border:2px solid #fff; box-shadow:0 1px 6px rgba(0,0,0,0.08); cursor:pointer;">
      </div>
      <form id="image-form" enctype="multipart/form-data" style="display:flex; gap:8px; justify-content:center;">
        <input type="file" id="image-upload" name="profile_image" accept="image/*" style="display:none;">
        <button type="button" onclick="document.getElementById('image-upload').click()" style="background:#007bff; color:#fff; border:none; padding:8px 14px; border-radius:4px; cursor:pointer;">Upload</button>
        <button type="button" id="remove-image" onclick="removeProfileImage()" style="background:#dc3545; color:#fff; border:none; padding:8px 14px; border-radius:4px; cursor:pointer;" <?php echo ($current_image_web === '' || $current_image_src === $default_image) ? 'disabled' : ''; ?>>Remove</button>
      </form>
    </div>

    <!-- Password card (scrollable content area) -->
    <div style="background:#f8f9fa; border:1px solid #e00e0; border-radius:6px; padding:12px; max-height: calc(100vh - 60px - 60px); overflow:auto;">
      <h3 style="margin:0 0 10px 0; font-size:16px; color:#333;">Change Password</h3>
      <!-- Verify current password -->
      <div id="passwordVerifySection">
        <form method="POST" action="" id="passwordVerifyForm" style="display:flex; flex-direction:column; gap:10px;">
          <div>
            <label style="display:block; margin-bottom:4px; font-weight:600; font-size:13px;">Current Password</label>
            <input type="password" name="verify_password" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
          </div>
          <div id="verifyPasswordError" style="color:#dc3545; font-size:12px; display:none;"></div>
          <div>
            <button type="submit" name="verify_password" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:14px;">Verify Password</button>
          </div>
        </form>
      </div>

      <!-- Change form hidden until verified -->
      <div id="passwordChangeFormSection" style="display:none;">
        <form method="POST" action="" id="passwordForm" style="display:flex; flex-direction:column; gap:10px;">
          <div>
            <label style="display:block; margin-bottom:4px; font-weight:600; font-size:13px;">New Password</label>
            <input type="password" name="new_password" id="newPassword" maxlength="20" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
          </div>
          <div id="newPasswordError" style="color:#dc3545; font-size:12px; display:none;"></div>
          <div>
            <label style="display:block; margin-bottom:4px; font-weight:600; font-size:13px;">Confirm New Password</label>
            <input type="password" name="confirm_password" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
          </div>
          <div id="confirmPasswordError" style="color:#dc3545; font-size:12px; display:none;"></div>
          <div style="display:flex; gap:8px;">
            <button type="submit" name="change_password" style="flex:1; padding:10px; background:#28a745; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:14px;">Update Password</button>
            <button type="button" id="cancelPasswordBtn" style="flex:1; padding:10px; background:#6c757d; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:14px;">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Right: profile data -->
  <div style="flex:1; background:#f8f9fa; border:1px solid #e0e0e0; border-radius:6px; padding:12px;">
    <h3 style="margin:0 0 10px 0; font-size:16px; color:#333;">Personal Information</h3>
    <form method="POST">
      <div style="margin-bottom:10px;">
        <label style="display:block; margin-bottom:4px; font-weight:600; font-size:13px;">Email</label>
        <input type="email" value="<?php echo htmlspecialchars($customer_data['Cust_Email'] ?? ''); ?>" disabled style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; background:#ececec;">
      </div>
      <div style="margin-bottom:10px; display:flex; gap:8px;">
        <div style="flex:1;">
          <label style="display:block; margin-bottom:4px; font-weight:600; font-size:13px;">First Name</label>
          <input type="text" name="first_name" value="<?php echo htmlspecialchars($customer_data['Cust_First_Name'] ?? ''); ?>" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
        </div>
        <div style="flex:1;">
          <label style="display:block; margin-bottom:4px; font-weight:600; font-size:13px;">Last Name</label>
          <input type="text" name="last_name" value="<?php echo htmlspecialchars($customer_data['Cust_Last_Name'] ?? ''); ?>" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
        </div>
      </div>
      <div style="margin-bottom:10px;">
        <label style="display:block; margin-bottom:4px; font-weight:600; font-size:13px;">Phone Number</label>
        <div style="display:flex; align-items:center;">
          <span style="background:#ececec; padding:8px; border:1px solid #ddd; border-right:none; border-radius:4px 0 0 4px; color:#666;">+60</span>
          <input type="tel" name="phone" value="<?php echo htmlspecialchars(str_replace('+60','',$customer_data['Cust_Phone'] ?? '')); ?>" placeholder="Enter phone without +60" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:0 4px 4px 0; border-left:none;">
        </div>
      </div>
      <button type="submit" name="update_profile" style="background:#28a745; color:#fff; border:none; padding:8px 14px; border-radius:4px; cursor:pointer;">Save Changes</button>
    </form>
  </div>
</div>

<!-- Image modal viewer -->
<div id="imageModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1002; align-items:center; justify-content:center;">
  <img id="imageModalImg" src="" alt="Profile Preview" style="max-width:80vw; max-height:80vh; border-radius:6px; box-shadow:0 6px 24px rgba(0,0,0,0.4);">
</div>

<script>
const profileImage = document.getElementById('profile-image');
const imageUpload = document.getElementById('image-upload');
const imageForm = document.getElementById('image-form');
const removeImageBtn = document.getElementById('remove-image');
const topMessageContent = document.getElementById('topMessageContent');

// modal preview
const imageModal = document.getElementById('imageModal');
const imageModalImg = document.getElementById('imageModalImg');
profileImage.addEventListener('click', () => { imageModalImg.src = profileImage.src.split('?')[0]; imageModal.style.display='flex'; });
imageModal.addEventListener('click', (e)=>{ if(e.target===imageModal){ imageModal.style.display='none'; }});

function showTopMessage(message, isSuccess = true) {
  topMessageContent.className = isSuccess ? 'success' : 'error';
  topMessageContent.innerHTML = (isSuccess ? '<img src="https://cdn-icons-png.flaticon.com/512/190/190411.png" style="width:16px;height:16px;margin-right:6px;vertical-align:middle;">' : '') + message;
  topMessageContent.style.opacity = '1';
  topMessageContent.style.transform = 'translateY(0)';
  setTimeout(()=>{ topMessageContent.style.opacity='0'; topMessageContent.style.transform='translateY(-14px)'; setTimeout(()=>{ topMessageContent.innerHTML=''; }, 300); }, 3000);
}

imageUpload.addEventListener('change', function(){
  const formData = new FormData(imageForm);
  fetch('', { method:'POST', body: formData })
    .then(r=>r.text()).then(t=>{ try{ return JSON.parse(t);}catch(e){ if(t.toLowerCase().includes('success')){ showTopMessage('Profile image updated successfully!', true); setTimeout(()=>location.reload(), 3500); return null;} throw e; }})
    .then(data=>{ if(!data) return; if(data.status==='success'){ profileImage.src=data.image+'?t='+(Date.now()); removeImageBtn.disabled=false; showTopMessage(data.message,true);} else { showTopMessage(data.message||'Failed to upload image.', false);} })
    .catch(()=> showTopMessage('Failed to upload image.', false));
});

function removeProfileImage(){
  if (removeImageBtn.disabled) return;
  fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'remove_image=1' })
    .then(r=>r.text()).then(t=>{ try{ return JSON.parse(t);}catch(e){ if(t.toLowerCase().includes('success')){ showTopMessage('Profile image removed successfully!', true); setTimeout(()=>location.reload(), 3500); return null;} throw e; }})
    .then(data=>{ if(!data) return; if(data.status==='success'){ profileImage.src=data.image+'?t='+(Date.now()); removeImageBtn.disabled=true; showTopMessage(data.message,true);} else { showTopMessage(data.message||'Failed to remove image.', false);} })
    .catch(()=> showTopMessage('Failed to remove image.', false));
}

// Password verify/change like admin
const passwordVerifyForm = document.getElementById('passwordVerifyForm');
const passwordForm = document.getElementById('passwordForm');
const passwordVerifySection = document.getElementById('passwordVerifySection');
const passwordChangeFormSection = document.getElementById('passwordChangeFormSection');
const verifyPasswordError = document.getElementById('verifyPasswordError');
const newPasswordError = document.getElementById('newPasswordError');
const confirmPasswordError = document.getElementById('confirmPasswordError');
const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');

passwordVerifyForm.addEventListener('submit', function(e){
  e.preventDefault();
  verifyPasswordError.style.display='none'; verifyPasswordError.textContent='';
  const formData = new FormData(passwordVerifyForm);
  fetch('', { method:'POST', body: formData })
    .then(r=>r.text())
    .then(data=>{
      if (data.includes('verification_success')){
        passwordVerifySection.style.display='none';
        passwordChangeFormSection.style.display='block';
        passwordVerifyForm.reset();
      } else if (data.toLowerCase().includes('incorrect')){
        verifyPasswordError.textContent='Current password is incorrect. Please try again.';
        verifyPasswordError.style.display='block';
      } else {
        showTopMessage('Failed to verify password.', false);
      }
    })
    .catch(()=> showTopMessage('Error verifying password.', false));
});

passwordForm.addEventListener('submit', function(){
  newPasswordError.style.display='none'; newPasswordError.textContent='';
  confirmPasswordError.style.display='none'; confirmPasswordError.textContent='';
});

cancelPasswordBtn.addEventListener('click', function(){
  passwordForm.reset();
  newPasswordError.style.display='none'; confirmPasswordError.style.display='none'; verifyPasswordError.style.display='none';
  passwordChangeFormSection.style.display='none'; passwordVerifySection.style.display='block';
});

// Render server messages on load
<?php if (!empty($messages)): foreach ($messages as $m): ?>
  showTopMessage('<?php echo addslashes($m); ?>', <?php echo (strpos($m,'success')!==false)?'true':'false'; ?>);
<?php endforeach; endif; ?>
</script>

<style>
#topMessageContent.success { color:#155724; font-weight:600; font-size:14px; padding:8px 10px; background:#d4edda; border:1px solid #c3e6cb; border-radius:4px; width:100%; box-sizing:border-box; }
#topMessageContent.error { color:#721c24; font-weight:600; font-size:14px; padding:8px 10px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:4px; width:100%; box-sizing:border-box; }
</style>