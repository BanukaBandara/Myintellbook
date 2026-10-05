<!-- resources/views/emails/verify-email.blade.php -->

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Email Verification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style type="text/css">
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
        }
        .header img {
            max-width: 100px;
        }
        .content {
            font-size: 16px;
            line-height: 1.6;
            color: #333333;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 20px 0;
            color: white !important;
            background-color:rgb(160, 56, 41);
            text-decoration: none;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #777777;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="{{ config('app.url') }}" target="_blank">
                <img src="{{ asset('logo.png') }}" alt="Logo">
            </a>
        </div>
        <div class="content">
            <h1>Confirm Your Email Address</h1>
            <p>Hi,</p>
            <p>Thank you for registering with {{ config('app.name') }}. Please click the button below to verify your email address.</p>
            <a href="{{ $actionUrl }}" class="button" target="_blank">Verify Email</a>
            <p>If you did not create an account, no further action is required.</p>
            <p>Thanks,<br>The {{ config('app.name') }} Team</p>
        </div>
        <div class="footer">
            <p>If you’re having trouble clicking the "Verify Email" button, copy and paste the URL below into your web browser:</p>
            <p><a href="{{ $actionUrl }}" target="_blank">{{ $actionUrl }}</a></p>
        </div>
    </div>
</body>
</html>
