<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Login</h2>

                <form action="<?php echo isset($form_action_path) ? htmlspecialchars($form_action_path) : ''; ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo isset($csrf_token) ? htmlspecialchars($csrf_token) : ''; ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label">Username:</label>
                        <input type="text" id="username" name="username" class="form-control" required autofocus value="<?php echo isset($username_value) ? htmlspecialchars($username_value) : ''; ?>">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password:</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Don't have an account? <a href="<?php echo isset($register_path) ? htmlspecialchars($register_path) : '#'; ?>">Sign Up</a></p>
                 <p class="mt-2 mb-0">Forgot password? <a href="<?php echo isset($forgot_password_path) ? htmlspecialchars($forgot_password_path) : '#'; ?>">Reset here</a></p>
            </div>
        </div>
    </div>
</div>
