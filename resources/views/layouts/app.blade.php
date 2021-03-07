<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>S-CRM Test Model</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" crossorigin="anonymous">
    @yield('additionalStyles')
</head>
<body>
<div id="app">
    <div class="container">
        <div class="navbar pl-0">
            <h2>S-CRM Test Model</h2>
        </div>
    </div>
    @yield('content')
</div>
</body>
@yield('preVueScripts')
<script src="{{asset('js/app.js')}}"></script>
</html>
