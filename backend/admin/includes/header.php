<?php
require_once __DIR__ . '/auth.php';
$adminUser = getAdminUser($pdo);
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Grand Frère Intelligent</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function exportTableToCSV(filename) {
            var csv = [];
            var rows = document.querySelectorAll("table tr");
            
            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll("td, th");
                // Skip the "Actions" column if it's the last one
                var limit = (rows[i].querySelector("th") && cols[cols.length-1].innerText.trim() === 'Actions' || cols[cols.length-1].innerText.trim() === 'Action') ? cols.length - 1 : cols.length;
                if (!rows[i].querySelector("th") && cols.length > 0 && cols[cols.length-1].querySelector(".dropdown, form")) {
                    limit = cols.length - 1; // Skip last column for data rows too
                }
                
                for (var j = 0; j < limit; j++) {
                    var data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/"/g, '""').trim();
                    row.push('"' + data + '"');
                }
                csv.push(row.join(","));
            }

            // Download CSV
            var csvFile = new Blob(["\uFEFF"+csv.join("\n")], {type: "text/csv;charset=utf-8;"});
            var downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
    </script>
    <style>
        body {
            background-color: #121212;
            color: #e0e0e0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        /* Sidebar styling */
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0; /* Height of navbar */
            box-shadow: inset -1px 0 0 rgba(255, 255, 255, .1);
            background-color: #1a1d20;
            transition: all 0.3s;
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .sidebar .nav-link {
            font-weight: 500;
            color: #adb5bd;
            padding: 0.8rem 1rem;
            margin: 0.2rem 1rem;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,0.05);
        }

        .sidebar .nav-link.active {
            color: #fff;
            background-color: #0d6efd;
            box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3);
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            font-size: 1.1rem;
        }

        /* Top Navbar */
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            background-color: #000 !important;
            padding: 0.75rem 1rem;
        }

        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* Main Content */
        main {
            padding-top: 60px; /* offset navbar */
        }

        /* Cards */
        .card {
            background-color: #1e2124;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-weight: 600;
        }

        /* Tables */
        .table {
            color: #e0e0e0;
        }
        .table-dark {
            --bs-table-bg: transparent;
        }
        
        /* Stats Cards */
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }

        /* Utilities */
        .text-gradient {
            background: linear-gradient(90deg, #0d6efd, #0dcaf0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        @media (max-width: 767.98px) {
            .sidebar {
                top: 5rem;
            }
        }
    </style>
</head>
<body>
    
<header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 fs-6 d-flex align-items-center" href="dashboard.php">
        <i class="bi bi-robot fs-4 me-2 text-primary"></i>
        <span>GFI Admin</span>
    </a>
    <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="navbar-nav w-100 d-flex justify-content-between flex-row align-items-center px-3">
        <div class="nav-item text-nowrap d-none d-md-block">
            <span class="text-secondary small">Serveur: TiragePromoBF</span>
        </div>
        <div class="nav-item text-nowrap">
            <a class="nav-link px-3" href="logout.php">
                <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
            </a>
        </div>
    </div>
</header>

<div class="container-fluid">
    <div class="row">
