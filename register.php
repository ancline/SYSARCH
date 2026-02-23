<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CCS Sit-in Monitoring System - Register</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f4;
}

/* Navbar */
.navbar {
    background-color: #9757d6;
    padding: 10px ;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
}


.nav-left {
    display: flex;
    align-items: center;
    gap: 5px; 
}

.nav-left img {
    height: 40px;
}

.navbar h1 {
    font-size: 18px;
    font-weight: normal;
}

.nav-links a {
    color: white;
    text-decoration: none;
    margin-left: 20px;
    font-size: 14px;
}

.nav-links a:hover {
    text-decoration: underline;
}

/* Main Content */
.main {
    padding: 40px 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 60px);
}

.registration-container {
    background-color: white;
    padding: 40px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 500px;
    width: 100%;
}

.back-btn {
    display: inline-block;
    padding: 8px 20px;
    background-color: #dc3545;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    margin-bottom: 20px;
    text-decoration: none;
}

.back-btn:hover {
    background-color: #c82333;
}

h2 {
    font-size: 28px;
    margin-bottom: 30px;
    color: #333;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #555;
    font-size: 14px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #1e4f91;
}

.register-btn {
    width: 100%;
    padding: 12px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    margin-top: 10px;
}

.register-btn:hover {
    background-color: #0056b3;
}
</style>
</head>
<body>

<div class="navbar">
    <div class="nav-left">
        <img src="uclogo-removebg-preview.png" alt="CCS Logo">
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
<div class="registration-container">
    
    <a href="landingpage.php" class="back-btn">Back</a>
<h2>Sign up</h2>

<form>
<div class="form-group">
    <label for="idNumber">ID Number</label>
    <input type="number" id="idNumber" name="idNumber">
</div>

<div class="form-group">
    <label for="lastName">Last Name</label>
    <input type="text" id="lastName" name="lastName">
</div>

<div class="form-group">
    <label for="firstName">First Name</label>
    <input type="text" id="firstName" name="firstName">
</div>

<div class="form-group">
    <label for="middleName">Middle Name</label>
    <input type="text" id="middleName" name="middleName">
</div>

<div class="form-group">
    <label for="courseLevel">Course Level</label>
    <select id="courseLevel" name="courseLevel">
    <option value="1">1</option>
    <option value="2">2</option>
    <option value="3">3</option>
    <option value="4">4</option>
    </select>
</div>

<div class="form-group">
    <label for="password">Password</label>
    <input type="password" id="password" name="password">
</div>

<div class="form-group">
    <label for="repeatPassword">Repeat your password</label>
    <input type="password" id="repeatPassword" name="repeatPassword">
</div>

<div class="form-group">
    <label for="email">Email</label>
    <input type="email" id="email" name="email">
</div>

<div class="form-group">
    <label for="course">Course</label>
    <input type="text" id="course" name="course" placeholder="BSIT">
</div>

<div class="form-group">
    <label for="address">Address</label>
    <input type="text" id="address" name="address">
</div>

<button type="submit" class="register-btn">Register</button>
</form>
</div>
</div>

</body>
</html>