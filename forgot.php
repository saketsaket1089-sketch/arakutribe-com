<?php include 'header.php'; ?>

<main>
    <style>
    .login-area {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: 85vh;
        padding-top: 60px;
        text-align: center;
    }
    .login-box {
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        width: 100%;
        max-width: 400px;
        margin-top: 20px;
    }
    .login-box input {
        width: 100%;
        padding: 10px;
        margin-top: 5px;
    }
    .login-box button {
        margin-top: 15px;
        padding: 10px 20px;
        width: 100%;
        background-color: #5a381e;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    .login-box a {
        display: block;
        margin-top: 10px;
        text-decoration: none;
        color: #5a381e;
    }
    .login-box h2 {
        margin-bottom: 20px;
    }
</style>

    <section class="login-area section-padding">
        <div class="login-box"><br><br>
          <h3>Reset Your Password</h3>
          <form action="admin_reset_password.php" method="POST">
            <input type="email" name="email" placeholder="Enter your email" required><br>
            <input type="password" name="new_password" placeholder="Enter new password" required><br>
            <button type="submit">Reset Password</button>
          </form>
         </div>
    </section>
    </main>
<?php include 'footer.php'; ?>
