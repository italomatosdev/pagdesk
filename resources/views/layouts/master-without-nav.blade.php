<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title> @yield('title') | PagDesk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="PagDesk - Sistema de Gestão de Crédito" name="description" />
    <meta content="Themesdesign" name="author" />
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ URL::asset('build/images/favicon.ico') }}">

    <!-- include head css -->
    @include('layouts.head-css')
</head>

<body>
    
    @yield('content')

    <!-- vendor-scripts -->
    @include('layouts.vendor-scripts')

</body>

</html>
