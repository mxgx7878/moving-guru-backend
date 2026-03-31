<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Dashboard - Material Connect')</title>
  @include('layout.css')
</head>
<body class="bg-body-tertiary">

  @include('layout.header')
  @include('layout.sidebar')

  <main class="layout-content">
    <div class="container-fluid">
      @yield('content')
    </div>
  </main>

  @include('layout.scripts')
</body>
</html>