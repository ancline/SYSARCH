<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CCS Sit-in Monitoring System</title>
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

.navbar {
            background-color: #9757d6;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 56px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-left img {
            height: 36px;
        }

        .navbar h1 {
            font-size: 15px;
            font-weight: 600;
            color: white;
            letter-spacing: 0.2px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 13.5px;
            padding: 6px 12px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .nav-links a:hover {
            background-color: rgba(255,255,255,0.15);
        }

        .nav-links a.active {
            background-color: rgba(255,255,255,0.12);
            font-weight: 600;
        }

/* MAIN SECTION */

.main{
display:flex;
justify-content:center;
align-items:center;
height:85vh;
}

/* CARD */

.welcome-card{
background:white;
padding:50px;
border-radius:10px;
box-shadow:0 10px 25px rgba(0,0,0,0.1);
text-align:center;
width:500px;
}

.welcome-card img{
width:150px;
margin-bottom:20px;
}

.welcome-card h2{
font-size:28px;
margin-bottom:15px;
color:#333;
}

.welcome-card p{
font-size:16px;
color:#666;
margin-bottom:25px;
}

.btn{
display:inline-block;
padding:10px 25px;
background:#9757d6;
color:white;
text-decoration:none;
border-radius:5px;
transition:0.3s;
}

.btn:hover{
background:#7c42b3;
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

<div class="welcome-card">

    <img src="ucmainccslogo.png">
    <h2>Welcome to Sit-in Monitoring System</h2>

    <p>Monitor student laboratory sit-ins, manage sessions, and track activity
    inside the College of Computer Studies laboratories.</p>

    <a href="login.php" class="btn">Get Started</a>

</div>

</div>

</body>
</html>