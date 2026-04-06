<!DOCTYPE html>
<?php require_once 'loading_screen.php';?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Icewind HVAC - Inventory System</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/header.css" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
<% if (typeof user !== 'undefined' && user) { %>

<!-- TOP NAV -->
<nav class="navbar navbar-dark bg-dark sticky-top px-3">
    <button class="btn btn-outline-light d-md-none" id="toggleSidebar">
        ☰
    </button>

    <a class="navbar-brand fw-bold" href="/dashboard">
        <i data-lucide="wind" class="me-2"></i>Icewind HVAC
    </a>

    <div class="ms-auto d-flex align-items-center">
        <span class="text-white me-3">Welcome, <%= user.username %></span>
        <a class="btn btn-outline-light btn-sm" href="/logout">Logout</a>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 sidebar">
            <div class="pt-3">

                <ul class="nav flex-column">

                    <li class="nav-item">
                        <a class="nav-link active" href="/dashboard">
                            <i data-lucide="layout-dashboard" class="me-2"></i>
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="/inventory">
                            <i data-lucide="package" class="me-2"></i>
                            Inventory
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="/consumables">
                            <i data-lucide="droplet" class="me-2"></i>
                            Consumables
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="/accessories">
                            <i data-lucide="settings" class="me-2"></i>
                            Accessories
                        </a>
                    </li>

                </ul>
            </div>
        </nav>

        <!-- MAIN CONTENT -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

<% } %>
<script>
    const toggleBtn = document.getElementById("toggleSidebar");
    const sidebar = document.getElementById("sidebarMenu");

    toggleBtn.addEventListener("click", () => {
        sidebar.classList.toggle("show");
    });
</script>
</body>
</html>