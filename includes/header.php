<?php
/**
 * HydroLogic OS – Shared HTML <head> Include
 * ============================================
 * Outputs the <!DOCTYPE>, <head>, and opens <body>.
 * Variables expected from the including page:
 *   $page_title (string) – used in <title> tag
 *   $active_page (string) – used by sidebar to highlight active link
 */
$page_title = $page_title ?? APP_NAME;
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="robots" content="noindex, nofollow">
    <title><?= sanitize($page_title) ?> – <?= APP_NAME ?></title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- HydroLogic Design System – Tailwind Config (matches original UI) -->
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "on-tertiary-container":     "#a38c6a",
                        "secondary":                 "#006c49",
                        "on-surface-variant":        "#45474c",
                        "surface-variant":           "#e4e2e3",
                        "primary":                   "#091426",
                        "error-container":           "#ffdad6",
                        "on-background":             "#1b1b1d",
                        "surface-container-highest": "#e4e2e3",
                        "on-secondary-fixed-variant":"#005236",
                        "on-secondary-container":    "#00714d",
                        "outline":                   "#75777d",
                        "surface":                   "#fbf8fa",
                        "surface-container":         "#f0edef",
                        "inverse-primary":           "#bcc7de",
                        "on-secondary-fixed":        "#002113",
                        "tertiary":                  "#1e1200",
                        "inverse-on-surface":        "#f3f0f2",
                        "on-tertiary-fixed-variant": "#564427",
                        "on-primary":                "#ffffff",
                        "on-error":                  "#ffffff",
                        "tertiary-container":        "#35260c",
                        "inverse-surface":           "#303032",
                        "surface-tint":              "#545f73",
                        "tertiary-fixed-dim":        "#ddc39d",
                        "secondary-container":       "#6cf8bb",
                        "error":                     "#ba1a1a",
                        "primary-fixed-dim":         "#bcc7de",
                        "outline-variant":           "#c5c6cd",
                        "surface-container-high":    "#eae7e9",
                        "on-error-container":        "#93000a",
                        "surface-container-low":     "#f5f3f4",
                        "on-secondary":              "#ffffff",
                        "on-primary-container":      "#8590a6",
                        "secondary-fixed-dim":       "#4edea3",
                        "surface-container-lowest":  "#ffffff",
                        "on-surface":                "#1b1b1d",
                        "primary-fixed":             "#d8e3fb",
                        "on-primary-fixed":          "#111c2d",
                        "tertiary-fixed":            "#fadfb8",
                        "on-tertiary-fixed":         "#271902",
                        "background":                "#fbf8fa",
                        "secondary-fixed":           "#6ffbbe",
                        "primary-container":         "#1e293b",
                        "surface-dim":               "#dcd9db",
                        "on-tertiary":               "#ffffff",
                        "surface-bright":            "#fbf8fa",
                        "on-primary-fixed-variant":  "#3c475a"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg":      "0.25rem",
                        "xl":      "0.5rem",
                        "full":    "0.75rem"
                    },
                    "spacing": {
                        "sidebar-width": "260px",
                        "2xl":  "48px",
                        "xs":   "4px",
                        "md":   "16px",
                        "sm":   "8px",
                        "gutter":"20px",
                        "unit": "4px",
                        "xl":   "32px",
                        "lg":   "24px"
                    },
                    "fontFamily": {
                        "h1":        ["Inter"],
                        "data-mono": ["JetBrains Mono"],
                        "body-lg":   ["Inter"],
                        "h2":        ["Inter"],
                        "body-md":   ["Inter"],
                        "h3":        ["Inter"],
                        "label-sm":  ["Inter"]
                    },
                    "fontSize": {
                        "h1":        ["30px", {"lineHeight":"38px",  "letterSpacing":"-0.02em","fontWeight":"700"}],
                        "data-mono": ["14px",  {"lineHeight":"20px",  "fontWeight":"500"}],
                        "body-lg":   ["16px",  {"lineHeight":"24px",  "fontWeight":"400"}],
                        "h2":        ["24px",  {"lineHeight":"32px",  "letterSpacing":"-0.01em","fontWeight":"600"}],
                        "body-md":   ["14px",  {"lineHeight":"20px",  "fontWeight":"400"}],
                        "h3":        ["20px",  {"lineHeight":"28px",  "fontWeight":"600"}],
                        "label-sm":  ["12px",  {"lineHeight":"16px",  "letterSpacing":"0.05em","fontWeight":"600"}]
                    }
                }
            }
        }
    </script>

    <!-- Custom Overrides -->
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        body { font-family: 'Inter', sans-serif; }

        /* Sidebar active state */
        .sidebar-active {
            background-color: #006c49 !important;
            color: #ffffff !important;
        }

        /* Smooth page transitions */
        main { animation: fadeIn 0.2s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
    </style>
</head>
<body class="bg-background font-body-md text-on-surface">
