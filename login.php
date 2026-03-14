<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - CCS Sit-in Monitoring System</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family: Arial, sans-serif;
    background:#f5f6fa;
}

/* NAVBAR */

.navbar{
    background:#9757d6;
    padding:10px 20px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    color:white;
}

.nav-left{
    display:flex;
    align-items:center;
    gap:5px;
}

.nav-left img{
    height:40px;
}

.navbar h1{
    font-size:18px;
    font-weight:normal;
}

.nav-links a{
    color:white;
    text-decoration:none;
    margin-left:20px;
    font-size:14px;
}

.nav-links a:hover{
    text-decoration:underline;
}

/* LOGIN SECTION */

.main{
    display:flex;
    justify-content:center;
    align-items:center;
    height:85vh;
}

.login-box{
    background:white;
    padding:40px;
    width:500px;
    border-radius:8px;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
    text-align:center;
}

.login-box h2{
    margin-bottom:20px;
    color:#333;
}

.input-group{
    margin-bottom:15px;
    text-align:left;
}

.input-group label{
    font-size:14px;
}

.input-group input{
    width:100%;
    padding:8px;
    margin-top:5px;
    border:1px solid #ccc;
    border-radius:4px;
}

.login-btn{
    width:100%;
    padding:10px;
    background:#9757d6;
    border:none;
    color:white;
    border-radius:5px;
    cursor:pointer;
}

.login-btn:hover{
    background:#7c42b3;
}

.register-link{
    margin-top:15px;
    font-size:14px;
}

.register-link a{
    color:#9757d6;
    text-decoration:none;
}

.register-link a:hover{
    text-decoration:underline;
}

</style>
</head>

<body>

<div class="navbar">

<div class="nav-left">
    <img src="uclogo-removebg-preview.png">
    <h1>College of Computer Studies Sit-in Monitoring System</h1>
</div>

<div class="nav-links">
    <a href="landingpage.php">Home</a>
    <a href="community.php">Community</a>
    <a href="about.php">About</a>
    <a href="login.php">Login</a>
    <a href="register.php">Register</a>
</div>

</div>


<div class="main">
<div class="login-box">

<h2>Login</h2>

<form action="student\home.php" method="POST">

<div class="input-group">
    <label>Username</label>
    <input type="text" name="username" required>
</div>

<div class="input-group">
    <label>Password</label>
    <input type="password" name="password" required>
</div>

<button class="login-btn" type="submit">Login</button>

</form>

<div class="register-link">Don't have an account? <a href="register.php">Register</a></div>

</div>

</div>

</body>
</html>