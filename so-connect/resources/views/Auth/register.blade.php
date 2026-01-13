<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Register</title>
</head>

<body>
    <h1> Register </h1>
    <br>
    <form action="/auth/register" method="POST">
        @csrf
        <label for="name">Name:</label>
        <br>
        <input type="text" name="name" id="name_reg">
        <br>
        <label for="email">Email:</label>
        <br>
        <input type="text" name="email" id="email_reg">
        <br>
        <label for="password">Password</label>
        <br>
        <input type="password" name="password" id="password_reg">
        <br>
        <button type="submit">Submit</button>
    </form>

</body>

</html>