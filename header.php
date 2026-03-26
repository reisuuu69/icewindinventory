<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Icewind HVAC - Inventory System</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
    --hvac-blue: #0056b3;
    --hvac-blue-dark: #003f88;
    --hvac-light-blue: #e7f1ff;
    --hvac-gray: #6c757d;
    --hvac-bg: #f4f6f9;
}

/* GLOBAL */
body {
    background-color: var(--hvac-bg);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* NAVBAR */
.navbar {
    background-color: var(--hvac-blue);
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

/* SIDEBAR */
.sidebar {
    min-height: calc(100vh - 56px);
    background-color: #ffffff;
    border-right: 1px solid #e5e7eb;
    padding-top: 10px;
    transition: all 0.3s ease;
}

/* NAV LINKS */
.sidebar .nav-link {
    color: #374151;
    padding: 12px 18px;
    margin: 4px 10px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    font-weight: 500;
    transition: all 0.2s ease;
}

/* ICON SPACING */
.sidebar .nav-link i {
    font-size: 18px;
    margin-right: 10px;
}

/* HOVER */
.sidebar .nav-link:hover {
    background-color: var(--hvac-light-blue);
    color: var(--hvac-blue);
    transform: translateX(3px);
}

/* ACTIVE */
.sidebar .nav-link.active {
    background: linear-gradient(90deg, var(--hvac-blue), var(--hvac-blue-dark));
    color: #fff;
    box-shadow: 0 4px 10px rgba(0,86,179,0.2);
}

/* CARD */
.card-stat {
    border-left: 4px solid var(--hvac-blue);
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

/* BUTTON */
.btn-primary {
    background-color: var(--hvac-blue);
    border-color: var(--hvac-blue);
    border-radius: 8px;
    font-weight: 500;
    transition: 0.2s;
}

.btn-primary:hover {
    background-color: var(--hvac-blue-dark);
    border-color: var(--hvac-blue-dark);
}

/* MOBILE SIDEBAR */
@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        top: 56px;
        left: -260px;
        width: 260px;
        height: 100%;
        z-index: 1050;
    }

    .sidebar.show {
        left: 0;
    }
}

/* OPTIONAL: SMOOTH CONTENT SHIFT */
main {
    transition: margin-left 0.3s ease;
}
    </style>
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