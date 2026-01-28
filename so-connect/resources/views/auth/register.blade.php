<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f6f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .register-container {
            background: #ffffff;
            padding: 30px 35px;
            width: 380px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        h1 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }

        label {
            font-size: 14px;
            font-weight: 600;
            color: #555;
        }

        input {
            width: 100%;
            padding: 10px 12px;
            margin: 8px 0 18px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15);
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            background-color: #46e56bff;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #4338ca;
        }
    </style>
</head>

<body>

    <div class="register-container">
        <h1>Register</h1>

        <form action="/register" method="POST">
            @csrf

            <label for="name">Name</label>
            <input type="text" name="name" id="name_reg" placeholder="Enter your name">

            <label for="email">Email</label>
            <input type="email" name="email" id="email_reg" placeholder="Enter your email">

            <label for="password">Password</label>
            <input type="password" name="password" id="password_reg" placeholder="Enter your password">

            <button type="submit">Submit</button>
        </form>
    </div>

</body>
</html>