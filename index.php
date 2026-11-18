<?php
/**
 * Aquarium Maintenance Dashboard
 * Shows all maintenance activities with their status
 */

// Load environment configuration
$config = require __DIR__ . '/env.php';
require_once __DIR__ . '/auth.php';

$authToken = getAuthToken($config);

// Handle logout
if (isset($_GET['logout'])) {
    clearAuthCookie($config);
    header('Location: index.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['password'] === $config['LOGIN_PASSWORD']) {
        setAuthCookie($authToken, $config);
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'Incorrect password';
    }
}

$isAuthenticated = isAuthenticated($config);

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2d6b7a">
    <?php require __DIR__ . '/pwa-head.php'; ?>
    <title>Aquarium Onderhoud Dashboard</title>
    <style>
        :root {
            --bg-start: #1a4a5e;
            --bg-mid: #2d6b7a;
            --bg-end: #3d8b96;
            --text-primary: #ffffff;
            --text-muted: #dceef1;
            --text-label: #c8e4e8;
            --text-value: #f2fafa;
            --text-on-light: #0f3d47;
            --accent: #7ad4de;
            --accent-soft: rgba(94, 184, 196, 0.35);
            --accent-bg: rgba(255, 255, 255, 0.22);
            --success: #a8e6be;
            --success-bg: rgba(126, 201, 160, 0.3);
            --warning: #f5d9a0;
            --warning-bg: rgba(232, 192, 122, 0.3);
            --danger: #f5b8b6;
            --danger-bg: rgba(232, 145, 143, 0.3);
            --neutral: #c0d4d8;
            --neutral-bg: rgba(138, 168, 176, 0.15);
            --surface: rgba(255, 255, 255, 0.16);
            --surface-hover: rgba(255, 255, 255, 0.22);
            --surface-input: rgba(15, 61, 71, 0.35);
            --border: rgba(255, 255, 255, 0.18);
            --radius-sm: 10px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --shadow-soft: 0 4px 24px rgba(20, 60, 80, 0.2);
            --shadow-card: 0 2px 12px rgba(20, 60, 80, 0.15);
            --glass-blur: blur(12px);
            --font-ui: system-ui, -apple-system, 'Segoe UI', sans-serif;
            --font-mono: 'Courier New', monospace;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-ui);
            background: linear-gradient(180deg, var(--bg-end) 0%, var(--bg-mid) 45%, var(--bg-start) 100%);
            background-attachment: fixed;
            min-height: 100vh;
            padding: 15px;
            color: var(--text-primary);
            position: relative;
            -webkit-font-smoothing: antialiased;
            padding-left: max(15px, env(safe-area-inset-left));
            padding-right: max(15px, env(safe-area-inset-right));
            padding-bottom: max(15px, env(safe-area-inset-bottom));
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 55%;
            background: radial-gradient(ellipse at 50% 0%, rgba(255, 255, 255, 0.1) 0%, transparent 65%);
            pointer-events: none;
            z-index: 0;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .header,
        .stat-card,
        .activity-card,
        .skeleton-card,
        .modal-content,
        .login-box,
        .refresh-btn,
        .logout-btn,
        .toast {
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
        }
        
        .header {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 15px 20px;
            margin-bottom: 15px;
            box-shadow: var(--shadow-card);
        }
        
        .header h1 {
            color: var(--text-primary);
            font-size: 22px;
            margin-bottom: 3px;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 3px rgba(15, 61, 71, 0.35);
        }
        
        .header p {
            color: var(--text-muted);
            font-size: 13px;
        }

        .header-meta {
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 6px;
        }
        
        .loading {
            display: none;
        }

        .skeleton-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 12px;
        }

        .skeleton-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 12px;
        }

        .skeleton-line {
            height: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            background: linear-gradient(90deg, var(--accent-soft) 25%, rgba(255, 255, 255, 0.15) 50%, var(--accent-soft) 75%);
            background-size: 200% 100%;
            animation: shimmer 2s ease-in-out infinite;
        }

        .skeleton-line.short { width: 40%; }
        .skeleton-line.medium { width: 70%; }
        .skeleton-line.long { width: 100%; }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        .error {
            background: var(--danger-bg);
            border: 1px solid rgba(232, 145, 143, 0.4);
            color: var(--danger);
            padding: 15px;
            border-radius: var(--radius-md);
            margin-bottom: 15px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 12px 15px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
            box-shadow: var(--shadow-card);
        }
        
        .stat-card:hover {
            background: var(--surface-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-soft);
        }

        .stat-card:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }
        
        .stat-card.active {
            background: var(--accent-bg);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 3px;
            font-family: var(--font-mono);
        }
        
        .stat-card .label {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        .stat-card.overdue .number { color: #ffd4d2; text-shadow: 0 1px 3px rgba(92, 32, 32, 0.4); }
        .stat-card.due-soon .number { color: #ffe8b8; text-shadow: 0 1px 3px rgba(92, 64, 32, 0.4); }
        .stat-card.ok .number { color: #c8f5dc; text-shadow: 0 1px 3px rgba(32, 80, 56, 0.4); }

        .stat-card.overdue.has-alerts {
            background: linear-gradient(135deg, rgba(245, 150, 148, 0.35), rgba(255, 255, 255, 0.14));
            border-color: rgba(245, 150, 148, 0.55);
            box-shadow: var(--shadow-card), 0 0 0 1px rgba(245, 150, 148, 0.35);
        }

        .stat-card.overdue.has-alerts .label {
            color: #ffe8e6;
            font-weight: 600;
        }
        
        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 12px;
        }
        
        .activity-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 14px;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            box-shadow: var(--shadow-card);
        }
        
        .activity-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-soft);
        }
        
        .activity-card.filtered-out {
            display: none;
        }
        
        .activity-card.status-overdue {
            background: linear-gradient(135deg, rgba(245, 180, 178, 0.28), rgba(232, 145, 143, 0.16));
            border-color: rgba(245, 150, 148, 0.5);
            box-shadow: var(--shadow-card), inset 0 0 0 1px rgba(245, 150, 148, 0.2);
        }

        .activity-card.status-overdue.needs-attention {
            animation: overdue-glow 3s ease-in-out infinite;
        }

        @keyframes overdue-glow {
            0%, 100% {
                box-shadow: var(--shadow-card), 0 0 0 1px rgba(245, 150, 148, 0.35);
            }
            50% {
                box-shadow: var(--shadow-soft), 0 0 0 2px rgba(245, 150, 148, 0.55), 0 0 16px rgba(245, 150, 148, 0.15);
            }
        }

        .activity-card.has-stock-alert {
            border-color: rgba(245, 180, 120, 0.45);
        }

        .activity-card.has-stock-empty {
            border-color: rgba(245, 150, 148, 0.55);
        }
        .activity-card.status-due-soon {
            background: linear-gradient(135deg, var(--surface), rgba(232, 192, 122, 0.12));
        }
        .activity-card.status-ok {
            background: linear-gradient(135deg, var(--surface), rgba(126, 201, 160, 0.12));
        }
        .activity-card.status-never-done {
            background: linear-gradient(135deg, var(--surface), rgba(255, 255, 255, 0.04));
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .activity-code {
            background: var(--accent-bg);
            border: none;
            padding: 4px 12px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            color: var(--text-on-light);
            font-family: var(--font-mono);
        }
        
        .activity-status {
            padding: 4px 10px;
            border: none;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .activity-status.overdue { 
            background: #f5a8a6;
            color: #5c1515;
            font-weight: 700;
        }
        .activity-status.due-soon { 
            background: var(--warning-bg);
            color: #5c4020;
        }
        .activity-status.ok { 
            background: var(--success-bg);
            color: var(--text-on-light);
        }
        .activity-status.never-done { 
            background: var(--neutral-bg);
            color: var(--text-on-light);
        }
        
        .activity-clickable {
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .activity-clickable:hover {
            opacity: 0.9;
        }
        
        .activity-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            text-shadow: 0 1px 2px rgba(15, 61, 71, 0.25);
        }
        
        .activity-details {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
        }
        
        .activity-details .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .activity-details .label {
            font-weight: 600;
            color: var(--text-label);
        }
        
        .activity-details .value {
            text-align: right;
            color: var(--text-value);
            font-weight: 500;
        }
        
        .stock-info {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }
        
        .activity-details .detail-row.is-overdue {
            margin-top: 4px;
            padding: 6px 8px;
            background: rgba(245, 150, 148, 0.25);
            border-radius: var(--radius-sm);
        }

        .activity-details .detail-row.is-overdue .value {
            color: #ffe8e6;
            font-weight: 700;
        }

        .stock-info.stock-out {
            background: rgba(245, 150, 148, 0.28);
            border: 1px solid rgba(245, 150, 148, 0.5);
            animation: stock-alert-pulse 2.5s ease-in-out infinite;
        }

        .stock-info.stock-low {
            background: rgba(245, 200, 120, 0.2);
            border: 1px solid rgba(245, 200, 120, 0.4);
        }

        @keyframes stock-alert-pulse {
            0%, 100% { background: rgba(245, 150, 148, 0.28); }
            50% { background: rgba(245, 150, 148, 0.38); }
        }

        .stock-info.stock-out .stock-label {
            color: #ffe8e6;
            font-weight: 600;
        }
        
        .stock-info.clickable {
            cursor: pointer;
            padding: 8px;
            margin: 10px -4px 0 -4px;
            border-radius: var(--radius-sm);
            transition: background 0.25s ease;
        }
        
        .stock-info.clickable:hover {
            background: var(--accent-bg);
        }

        .stock-info.stock-out.clickable:hover,
        .stock-info.stock-low.clickable:hover {
            filter: brightness(1.05);
        }
        
        .stock-right {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .stock-days {
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap;
        }

        .stock-badge {
            padding: 3px 10px;
            border: none;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 600;
            font-family: var(--font-mono);
        }
        
        .stock-badge.out { 
            background: #f0807e;
            color: #fff;
            font-weight: 700;
            padding: 4px 12px;
            box-shadow: 0 1px 4px rgba(92, 21, 21, 0.25);
        }
        .stock-badge.low { 
            background: #e8b86a;
            color: #5c4020;
            font-weight: 700;
        }
        .stock-badge.ok { 
            background: var(--success-bg);
            color: var(--text-on-light);
        }

        .card-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .quick-done-btn {
            flex: 1;
            padding: 10px 12px;
            background: var(--success-bg);
            color: var(--text-on-light);
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.25s ease;
            font-family: var(--font-ui);
        }

        .quick-done-btn:hover {
            background: rgba(126, 201, 160, 0.35);
        }

        .quick-done-btn:focus-visible,
        .quick-register-btn:focus-visible,
        .refresh-btn:focus-visible,
        .logout-btn:focus-visible,
        .modal-btn:focus-visible,
        .inventory-btn:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }

        .quick-done-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .quick-register-btn {
            flex: 1;
            padding: 10px 12px;
            background: var(--accent-bg);
            color: var(--text-on-light);
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.25s ease;
            font-family: var(--font-ui);
        }
        
        .quick-register-btn:hover {
            background: rgba(94, 184, 196, 0.3);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(26, 74, 94, 0.6);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: rgba(45, 107, 122, 0.85);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
            max-width: 450px;
            width: 90%;
            box-shadow: var(--shadow-soft);
            animation: modalSlideIn 0.35s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }
        
        .modal-header h2 {
            color: var(--text-primary);
            font-size: 16px;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            color: var(--text-muted);
            cursor: pointer;
            line-height: 1;
            padding: 0;
            width: 24px;
            height: 24px;
        }
        
        .modal-close:hover {
            color: var(--warning);
        }
        
        .modal-body {
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
            font-size: 12px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-family: var(--font-ui);
            background: var(--surface-input);
            color: var(--text-primary);
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.35);
            box-shadow: 0 0 0 2px var(--accent-soft);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 50px;
        }
        
        .inventory-buttons {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .inventory-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: var(--radius-sm);
            background: var(--surface);
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.25s ease;
            color: var(--text-muted);
            font-family: var(--font-ui);
        }
        
        .inventory-btn.active {
            background: var(--accent-bg);
            color: var(--text-on-light);
        }
        
        .inventory-btn:hover:not(.active) {
            background: var(--surface-hover);
        }
        
        .quantity-input-field {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            font-family: var(--font-mono);
            background: var(--surface-input);
            color: var(--text-primary);
        }
        
        .quantity-input-field:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.35);
            box-shadow: 0 0 0 2px var(--accent-soft);
        }
        
        .form-group small {
            display: block;
            color: var(--text-muted);
            margin-top: 4px;
            font-size: 11px;
        }
        
        .modal-actions {
            display: flex;
            gap: 8px;
        }
        
        .modal-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.25s ease;
            font-family: var(--font-ui);
        }
        
        .modal-btn-primary {
            background: var(--accent-bg);
            color: var(--text-on-light);
        }
        
        .modal-btn-primary:hover {
            background: rgba(94, 184, 196, 0.3);
        }
        
        .modal-btn-primary:disabled {
            background: var(--surface);
            color: var(--neutral);
            cursor: not-allowed;
        }
        
        .modal-btn-secondary {
            background: var(--surface);
            color: var(--text-muted);
        }
        
        .modal-btn-secondary:hover {
            background: var(--surface-hover);
        }
        
        .modal-alert {
            padding: 10px;
            border-radius: var(--radius-sm);
            margin-bottom: 12px;
            font-size: 12px;
        }
        
        .modal-alert.success {
            background: var(--success-bg);
            color: var(--success);
            border: none;
        }

        .modal-alert.error {
            background: var(--danger-bg);
            color: var(--danger);
            border: none;
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 50%;
            width: 48px;
            height: 48px;
            font-size: 20px;
            cursor: pointer;
            box-shadow: var(--shadow-card);
            transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
            color: var(--text-primary);
        }
        
        .refresh-btn:hover {
            background: var(--surface-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-soft);
        }
        
        .refresh-btn:active {
            transform: scale(0.95);
        }
        
        /* Login Screen */
        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        
        .login-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 32px;
            max-width: 400px;
            width: 100%;
            box-shadow: var(--shadow-soft);
        }
        
        .login-box h1 {
            color: var(--text-primary);
            font-size: 24px;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .login-box p {
            color: var(--text-muted);
            font-size: 12px;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .login-form .form-group {
            margin-bottom: 20px;
        }
        
        .login-form label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 12px;
        }
        
        .login-form input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--font-ui);
            background: var(--surface-input);
            color: var(--text-primary);
        }
        
        .login-form input[type="password"]:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.35);
            box-shadow: 0 0 0 2px var(--accent-soft);
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: var(--radius-sm);
            background: var(--accent-bg);
            color: var(--text-on-light);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.25s ease;
            font-family: var(--font-ui);
        }
        
        .login-btn:hover {
            background: rgba(94, 184, 196, 0.3);
        }
        
        .login-btn:active {
            transform: scale(0.98);
        }

        .pwa-install-btn {
            margin-top: 12px;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .pwa-install-btn:hover {
            background: var(--surface-hover);
        }

        .pwa-install-btn:active {
            transform: scale(0.98);
        }

        .pwa-install-btn--login {
            width: 100%;
        }

        .pwa-install-btn--header {
            margin-top: 16px;
        }
        
        .login-error {
            background: var(--danger-bg);
            border: none;
            color: var(--danger);
            padding: 10px;
            border-radius: var(--radius-sm);
            margin-bottom: 15px;
            font-size: 12px;
            text-align: center;
        }
        
        .logout-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 8px 16px;
            font-size: 11px;
            cursor: pointer;
            color: var(--text-muted);
            text-decoration: none;
            box-shadow: var(--shadow-card);
            transition: transform 0.25s ease, background 0.25s ease, color 0.25s ease;
            z-index: 10;
        }
        
        .logout-btn:hover {
            background: var(--surface-hover);
            color: var(--text-primary);
        }

        .filter-bar {
            display: none;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
            align-items: center;
        }

        .filter-bar.active {
            display: flex;
        }

        .filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.15);
            border: none;
            border-radius: 20px;
            font-size: 11px;
            color: var(--text-primary);
        }

        .filter-pill button {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            padding: 0;
        }

        .filter-pill button:hover {
            color: var(--text-primary);
        }

        .filter-clear-all {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 11px;
            cursor: pointer;
            text-decoration: underline;
        }

        .toast {
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: rgba(15, 61, 71, 0.92);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
            padding: 12px 24px;
            border-radius: var(--radius-lg);
            font-size: 13px;
            box-shadow: var(--shadow-soft);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease, transform 0.3s ease;
            z-index: 2000;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .info-box {
            font-size: 13px;
            color: var(--text-primary);
            padding: 10px;
            background: var(--surface-input);
            border: none;
            border-radius: var(--radius-sm);
        }
        
        @media (max-width: 768px) {
            .activities-grid {
                grid-template-columns: 1fr;
            }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php if (!$isAuthenticated): ?>
    <!-- Login Screen -->
    <div class="login-container">
        <div class="login-box">
            <h1>🐠 Aquarium</h1>
            <p>Inloggen vereist</p>
            
            <?php if (isset($loginError)): ?>
            <div class="login-error"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit" name="login" class="login-btn">Enter</button>
            </form>
            <button type="button" class="pwa-install-btn pwa-install-btn--login" hidden>Install app</button>
        </div>
    </div>
    <?php else: ?>
    <!-- Dashboard -->
    <a href="#" class="logout-btn" onclick="handleLogout(); return false;">Logout</a>
    
    <div class="container">
        <div class="header">
            <h1>🐠 Juwel Aquarium Onderhoud</h1>
            <p>Overzicht van alle onderhoudsactiviteiten</p>
            <button type="button" class="pwa-install-btn pwa-install-btn--header" hidden>Install app</button>
            <div id="lastUpdated" class="header-meta"></div>
        </div>
        
        <div id="stats" class="stats"></div>
        <div id="filterBar" class="filter-bar"></div>
        <div id="error" class="error" style="display: none;"></div>
        <div id="loading" class="skeleton-grid">
            <div class="skeleton-card"><div class="skeleton-line short"></div><div class="skeleton-line long"></div><div class="skeleton-line medium"></div></div>
            <div class="skeleton-card"><div class="skeleton-line short"></div><div class="skeleton-line long"></div><div class="skeleton-line medium"></div></div>
            <div class="skeleton-card"><div class="skeleton-line short"></div><div class="skeleton-line long"></div><div class="skeleton-line medium"></div></div>
        </div>
        <div id="activities" class="activities-grid"></div>
    </div>
    
    <div id="toast" class="toast"></div>
    
    <button class="refresh-btn" onclick="loadDashboard()" title="Ververs">↻</button>
    
    <!-- Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Onderhoud Registreren</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modalAlert" class="modal-alert" style="display: none;"></div>
                <form id="quickRegisterForm">
                    <input type="hidden" id="modalActivityCode" name="code">
                    <div class="form-group">
                        <label>Activiteit</label>
                        <div id="modalActivityName" class="info-box"></div>
                    </div>
                    <div class="form-group">
                        <label for="modalDate">Datum *</label>
                        <input type="date" id="modalDate" name="date" required>
                    </div>
                    <div class="form-group">
                        <label for="modalTime">Tijd</label>
                        <input type="time" id="modalTime" name="time">
                        <small>Optioneel - voor nauwkeurige tijdregistratie</small>
                    </div>
                    <div class="form-group">
                        <label for="modalNotes">Notities</label>
                        <textarea id="modalNotes" name="notes" placeholder="Extra informatie..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal()">Annuleren</button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="submitQuickRegister()" id="modalSubmitBtn">Registreren</button>
            </div>
        </div>
    </div>
    
    <!-- Inventory Modal -->
    <div id="inventoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Voorraad Beheren</h2>
                <button class="modal-close" onclick="closeInventoryModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="inventoryModalAlert" class="modal-alert" style="display: none;"></div>
                
                <div class="form-group">
                    <label>Filter Materiaal</label>
                    <div id="inventoryActivityName" class="info-box"></div>
                </div>
                
                <div class="form-group">
                    <label>Actie</label>
                    <div class="inventory-buttons">
                        <button type="button" class="inventory-btn active" onclick="setInventoryAction('add')" id="btnAdd" title="Voorraad toevoegen">
                            + Toevoegen
                        </button>
                        <button type="button" class="inventory-btn" onclick="setInventoryAction('remove')" id="btnRemove" title="Voorraad verwijderen">
                            − Verwijderen
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label id="quantityLabel">Aantal toevoegen</label>
                    <input type="number" class="quantity-input-field" id="quantityInput" value="1" min="0" step="1">
                </div>
                
                <div class="form-group">
                    <label>Huidige voorraad</label>
                    <div id="currentStockDisplay" class="info-box" style="font-size: 18px; font-weight: bold; text-align: center;"></div>
                </div>
                
                <div class="form-group">
                    <label for="inventoryNotes">Notities</label>
                    <textarea id="inventoryNotes" placeholder="Bijv: Gekocht bij ..."></textarea>
                </div>
                
                <input type="hidden" id="inventoryActivityCode">
                <input type="hidden" id="inventoryAction" value="add">
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeInventoryModal()">Annuleren</button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="submitInventoryUpdate()" id="inventorySubmitBtn">Bijwerken</button>
            </div>
        </div>
    </div>
    
    <script>
        // API endpoint
        const API_URL = 'api.php';
        const AUTH_TOKEN_STORAGE_KEY = 'aquariumAuthToken';

        function clearStoredAuthToken() {
            localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY);
        }

        function handleLogout() {
            clearStoredAuthToken();
            window.location.replace('index.php?logout=1');
        }
        
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2500);
        }

        function updateLastRefreshed() {
            const el = document.getElementById('lastUpdated');
            const now = new Date();
            el.textContent = 'Bijgewerkt om ' + now.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
        }

        const statusLabels = {
            'overdue': 'Te laat',
            'due_soon': 'Binnenkort',
            'ok': 'OK',
            'never_done': 'Nooit gedaan'
        };
        
        /**
         * Fetch data from API
         */
        async function fetchAPI(endpoint, method = 'GET', data = null) {
            try {
                const options = {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    }
                };
                
                if (data && method === 'POST') {
                    options.body = JSON.stringify(data);
                }
                
                const response = await fetch(`${API_URL}?endpoint=${endpoint}`, options);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error || 'Unknown error');
                }
                return result.data || result;
            } catch (error) {
                console.error('API Error:', error);
                throw error;
            }
        }
        
        /**
         * Format date to Dutch format
         */
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('nl-NL', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        }
        
        /**
         * Get days text with proper Dutch grammar
         */
        function getDaysText(days) {
            if (days === null || days === undefined) return '-';
            if (days < 0) return `${Math.abs(days)} dagen te laat`;
            if (days === 0) return 'Vandaag';
            if (days === 1) return 'Morgen';
            return `Over ${days} dagen`;
        }

        /**
         * Event-based DOI for a shared material group: walk upcoming usages
         * chronologically until stock is exhausted; return day of last use.
         */
        function getDaysOfInventoryForGroup(members, stock) {
            if (stock === null || stock === undefined || stock <= 0) return 0;
            if (!members || members.length === 0) return 0;

            const nextDays = members.map(m => {
                if (!m.next_due_date) return 0;
                return Math.max(0, m.days_until_due ?? 0);
            });
            const intervals = members.map(m => (m.frequency_weeks || 0) * 7);

            let remaining = stock;
            let lastDay = 0;

            while (remaining > 0) {
                let earliestIdx = 0;
                for (let i = 1; i < nextDays.length; i++) {
                    if (nextDays[i] < nextDays[earliestIdx]) earliestIdx = i;
                }
                lastDay = nextDays[earliestIdx];
                remaining -= 1;
                if (remaining === 0) return lastDay;
                const interval = intervals[earliestIdx];
                nextDays[earliestIdx] = lastDay + (interval > 0 ? interval : 7);
            }

            return lastDay;
        }

        /**
         * Estimate days of inventory remaining; combines siblings that share material_group
         */
        function getDaysOfInventory(activity, allActivities) {
            const stock = activity.current_stock;
            if (stock === null || stock === undefined || stock <= 0) return 0;

            let members = [activity];
            if (activity.material_group && Array.isArray(allActivities)) {
                const siblings = allActivities.filter(
                    a => a.is_filter_material == 1 && a.material_group === activity.material_group
                );
                if (siblings.length > 0) members = siblings;
            }

            return getDaysOfInventoryForGroup(members, stock);
        }
        
        /**
         * Calculate statistics
         */
        function calculateStats(activities) {
            const stats = {
                overdue: 0,
                due_soon: 0,
                ok: 0,
                total: activities.length
            };
            
            activities.forEach(activity => {
                if (activity.status === 'overdue') stats.overdue++;
                else if (activity.status === 'due_soon') stats.due_soon++;
                else if (activity.status === 'ok') stats.ok++;
            });
            
            return stats;
        }
        
        /**
         * Render statistics cards
         */
        function renderStats(stats) {
            const statsContainer = document.getElementById('stats');
            statsContainer.innerHTML = `
                <div class="stat-card overdue${stats.overdue > 0 ? ' has-alerts' : ''}" onclick="toggleFilter('overdue')" title="Klik om te filteren">
                    <div class="number">${stats.overdue}</div>
                    <div class="label">Te laat</div>
                </div>
                <div class="stat-card due-soon" onclick="toggleFilter('due_soon')" title="Klik om te filteren">
                    <div class="number">${stats.due_soon}</div>
                    <div class="label">Binnenkort</div>
                </div>
                <div class="stat-card ok" onclick="toggleFilter('ok')" title="Klik om te filteren">
                    <div class="number">${stats.ok}</div>
                    <div class="label">OK</div>
                </div>
                <div class="stat-card" onclick="clearFilters()" title="Klik om alle filters te verwijderen">
                    <div class="number">${stats.total}</div>
                    <div class="label">Totaal</div>
                </div>
            `;
            
            // Restore active state based on current filters
            updateStatCardStates();
        }
        
        /**
         * Render activity card
         */
        function renderActivityCard(activity, allActivities) {
            const statusClass = activity.status.replace('_', '-');
            const statusLabel = statusLabels[activity.status] || activity.status;
            const isOverdue = activity.status === 'overdue';
            
            let stockHTML = '';
            let stockAlertClass = '';
            if (activity.is_filter_material == 1 && activity.current_stock !== null) {
                const stockClass = activity.current_stock <= 0 ? 'out' : 
                                   activity.current_stock <= 2 ? 'low' : 'ok';
                if (activity.current_stock <= 0) {
                    stockAlertClass = 'has-stock-empty';
                } else if (activity.current_stock <= 2) {
                    stockAlertClass = 'has-stock-alert';
                }
                const stockRowClass = activity.current_stock <= 0 ? 'stock-out' :
                                      activity.current_stock <= 2 ? 'stock-low' : '';
                const stockLabel = activity.current_stock <= 0 ? 'Voorraad leeg' : 'Voorraad:';
                const stockBadgeText = activity.current_stock <= 0 ? 'Geen voorraad' : `${activity.current_stock} stuks`;
                const daysOfInventory = getDaysOfInventory(activity, allActivities);
                const daysFragment = activity.current_stock > 0
                    ? `<span class="stock-days">· nog ${daysOfInventory} dagen</span>`
                    : '';
                stockHTML = `
                    <div class="stock-info clickable ${stockRowClass}" onclick='openInventoryModal(${JSON.stringify(activity)})' title="Klik om voorraad aan te passen">
                        <span class="stock-label">${stockLabel}</span>
                        <span class="stock-right">
                            <span class="stock-badge ${stockClass}">${stockBadgeText}</span>
                            ${daysFragment}
                        </span>
                    </div>
                `;
            }

            const cardClasses = [
                'activity-card',
                `status-${statusClass}`,
                isOverdue ? 'needs-attention' : '',
                stockAlertClass
            ].filter(Boolean).join(' ');
            
            return `
                <div class="${cardClasses}" data-status="${activity.status}">
                    <div class="activity-clickable" onclick='openRegisterModal(${JSON.stringify(activity)})' title="Klik om onderhoud te registreren">
                        <div class="activity-header">
                            <span class="activity-code">${activity.code}</span>
                            <span class="activity-status ${activity.status}">${statusLabel}</span>
                        </div>
                        <div class="activity-title">${activity.description}</div>
                        <div class="activity-details">
                            <div class="detail-row">
                                <span class="label">Frequentie:</span>
                                <span class="value">${activity.frequency_weeks} weken</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Laatst gedaan:</span>
                                <span class="value">${formatDate(activity.last_performed)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Volgende:</span>
                                <span class="value">${formatDate(activity.next_due_date)}</span>
                            </div>
                            <div class="detail-row${isOverdue ? ' is-overdue' : ''}">
                                <span class="label">Status:</span>
                                <span class="value"><strong>${getDaysText(activity.days_until_due)}</strong></span>
                            </div>
                        </div>
                    </div>
                    ${stockHTML}
                    <div class="card-actions">
                        <button type="button" class="quick-done-btn" onclick='quickDoneToday(${JSON.stringify(activity)}, event)' title="Registreer als vandaag gedaan">Vandaag gedaan</button>
                        <button type="button" class="quick-register-btn" onclick='openRegisterModal(${JSON.stringify(activity)})' title="Registreer met datum en notities">Details</button>
                    </div>
                </div>
            `;
        }
        
        /**
         * Load and render dashboard
         */
        async function loadDashboard() {
            const loadingEl = document.getElementById('loading');
            const errorEl = document.getElementById('error');
            const activitiesEl = document.getElementById('activities');
            
            try {
                loadingEl.style.display = 'grid';
                errorEl.style.display = 'none';
                activitiesEl.innerHTML = '';
                
                const activities = await fetchAPI('latest_maintenance');
                
                const stats = calculateStats(activities);
                renderStats(stats);
                
                activitiesEl.innerHTML = activities.map(activity => 
                    renderActivityCard(activity, activities)
                ).join('');
                
                loadingEl.style.display = 'none';
                updateLastRefreshed();
                updateFilterBar();
                
            } catch (error) {
                loadingEl.style.display = 'none';
                errorEl.style.display = 'block';
                errorEl.textContent = `Fout bij laden: ${error.message}`;
                console.error('Dashboard error:', error);
            }
        }

        async function quickDoneToday(activity, event) {
            if (event) {
                event.stopPropagation();
            }
            const btn = event ? event.currentTarget : null;
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Bezig...';
            }
            try {
                const now = new Date();
                const date = now.toISOString().split('T')[0];
                await fetchAPI('record_maintenance', 'POST', {
                    code: activity.code,
                    date: date,
                    notes: null
                });
                showToast('✓ ' + activity.code + ' geregistreerd voor vandaag');
                await loadDashboard();
            } catch (error) {
                showToast('Fout: ' + error.message);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Vandaag gedaan';
                }
            }
        }
        
        /**
         * Open register modal for specific activity
         */
        function openRegisterModal(activity) {
            const modal = document.getElementById('registerModal');
            const modalAlert = document.getElementById('modalAlert');
            
            // Reset form
            document.getElementById('quickRegisterForm').reset();
            modalAlert.style.display = 'none';
            
            // Set activity info
            document.getElementById('modalActivityCode').value = activity.code;
            document.getElementById('modalActivityName').textContent = `${activity.code} - ${activity.description}`;
            
            // Set current date and time (GMT+1)
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            document.getElementById('modalDate').value = `${year}-${month}-${day}`;
            document.getElementById('modalTime').value = `${hours}:${minutes}`;
            
            // Show modal
            modal.classList.add('active');
        }
        
        /**
         * Close modal
         */
        function closeModal() {
            const modal = document.getElementById('registerModal');
            const submitBtn = document.getElementById('modalSubmitBtn');
            const modalAlert = document.getElementById('modalAlert');
            
            modal.classList.remove('active');
            
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.textContent = 'Registreren';
            
            // Hide alert
            modalAlert.style.display = 'none';
        }
        
        /**
         * Submit quick register form
         */
        async function submitQuickRegister() {
            const submitBtn = document.getElementById('modalSubmitBtn');
            const modalAlert = document.getElementById('modalAlert');
            
            try {
                // Disable button
                submitBtn.disabled = true;
                submitBtn.textContent = 'Bezig...';
                modalAlert.style.display = 'none';
                
                // Get form values
                const code = document.getElementById('modalActivityCode').value;
                const date = document.getElementById('modalDate').value;
                const time = document.getElementById('modalTime').value;
                const notes = document.getElementById('modalNotes').value.trim();
                
                // Combine date and time if time is provided
                let performedDate = date;
                if (time) {
                    performedDate = `${date} ${time}:00`;
                }
                
                // Submit to API
                await fetchAPI('record_maintenance', 'POST', {
                    code: code,
                    date: performedDate,
                    notes: notes || null
                });
                
                // Show success
                modalAlert.className = 'modal-alert success';
                modalAlert.textContent = '✓ Onderhoud succesvol geregistreerd!';
                modalAlert.style.display = 'block';
                
                // Reload dashboard after short delay
                setTimeout(() => {
                    closeModal();
                    loadDashboard();
                }, 1000);
                
            } catch (error) {
                modalAlert.className = 'modal-alert error';
                modalAlert.textContent = 'Fout: ' + error.message;
                modalAlert.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Registreren';
            }
        }
        
        /**
         * Open inventory modal for specific activity
         */
        function openInventoryModal(activity) {
            const modal = document.getElementById('inventoryModal');
            const modalAlert = document.getElementById('inventoryModalAlert');
            
            // Reset form
            modalAlert.style.display = 'none';
            document.getElementById('inventoryNotes').value = '';
            
            // Set activity info
            document.getElementById('inventoryActivityCode').value = activity.code;
            document.getElementById('inventoryActivityName').textContent = `${activity.code} - ${activity.description}`;
            document.getElementById('currentStockDisplay').textContent = `${activity.current_stock} stuks`;
            
            // Store current stock for calculations
            modal.dataset.currentStock = activity.current_stock;
            
            // Reset to add mode
            setInventoryAction('add');
            document.getElementById('quantityInput').value = '1';
            
            // Show modal
            modal.classList.add('active');
        }
        
        /**
         * Close inventory modal
         */
        function closeInventoryModal() {
            const modal = document.getElementById('inventoryModal');
            const submitBtn = document.getElementById('inventorySubmitBtn');
            
            modal.classList.remove('active');
            
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.textContent = 'Bijwerken';
        }
        
        /**
         * Set inventory action (add/remove)
         */
        function setInventoryAction(action) {
            document.getElementById('inventoryAction').value = action;
            
            // Update button states
            document.getElementById('btnAdd').classList.toggle('active', action === 'add');
            document.getElementById('btnRemove').classList.toggle('active', action === 'remove');
            
            // Update label
            const label = document.getElementById('quantityLabel');
            if (action === 'add') {
                label.textContent = 'Aantal toevoegen';
            } else {
                label.textContent = 'Aantal verwijderen';
            }
        }
        
        /**
         * Submit inventory update
         */
        async function submitInventoryUpdate() {
            const submitBtn = document.getElementById('inventorySubmitBtn');
            const modalAlert = document.getElementById('inventoryModalAlert');
            
            try {
                // Disable button
                submitBtn.disabled = true;
                submitBtn.textContent = 'Bezig...';
                modalAlert.style.display = 'none';
                
                // Get form values
                const code = document.getElementById('inventoryActivityCode').value;
                const action = document.getElementById('inventoryAction').value;
                const quantity = parseInt(document.getElementById('quantityInput').value);
                const notes = document.getElementById('inventoryNotes').value.trim();
                
                // Validate quantity
                if (isNaN(quantity) || quantity < 0) {
                    throw new Error('Voer een geldig aantal in');
                }
                
                // Calculate the change
                let quantityChange;
                let transactionType;
                
                if (action === 'add') {
                    quantityChange = quantity;
                    transactionType = 'purchase';
                } else { // remove
                    quantityChange = -quantity;
                    transactionType = 'usage';
                }
                
                // Submit to API
                await fetchAPI('update_inventory', 'POST', {
                    code: code,
                    quantity: quantityChange,
                    type: transactionType,
                    notes: notes || null
                });
                
                // Show success
                modalAlert.className = 'modal-alert success';
                modalAlert.textContent = '✓ Voorraad succesvol bijgewerkt!';
                modalAlert.style.display = 'block';
                
                // Reload dashboard after short delay
                setTimeout(() => {
                    closeInventoryModal();
                    loadDashboard();
                }, 1000);
                
            } catch (error) {
                modalAlert.className = 'modal-alert error';
                modalAlert.textContent = 'Fout: ' + error.message;
                modalAlert.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Bijwerken';
            }
        }
        
        // Filter state
        let activeFilters = new Set();
        
        function updateFilterBar() {
            const filterBar = document.getElementById('filterBar');
            if (activeFilters.size === 0) {
                filterBar.classList.remove('active');
                filterBar.innerHTML = '';
                return;
            }
            const pills = Array.from(activeFilters).map(status => `
                <span class="filter-pill">
                    Filter: ${statusLabels[status] || status}
                    <button type="button" onclick="toggleFilter('${status}')" aria-label="Filter verwijderen">&times;</button>
                </span>
            `).join('');
            filterBar.innerHTML = pills + '<button type="button" class="filter-clear-all" onclick="clearFilters()">Alles wissen</button>';
            filterBar.classList.add('active');
        }

        /**
         * Toggle filter for a status
         */
        function toggleFilter(status) {
            if (activeFilters.has(status)) {
                activeFilters.delete(status);
            } else {
                activeFilters.add(status);
            }
            applyFilters();
            updateStatCardStates();
            updateFilterBar();
        }
        
        /**
         * Clear all filters
         */
        function clearFilters() {
            activeFilters.clear();
            applyFilters();
            updateStatCardStates();
            updateFilterBar();
        }
        
        /**
         * Apply filters to activity cards
         */
        function applyFilters() {
            const cards = document.querySelectorAll('.activity-card');
            
            cards.forEach(card => {
                const status = card.dataset.status;
                
                if (activeFilters.size === 0) {
                    // No filters active - show all
                    card.classList.remove('filtered-out');
                } else if (activeFilters.has(status)) {
                    // Status is in active filters - show
                    card.classList.remove('filtered-out');
                } else {
                    // Status not in active filters - hide
                    card.classList.add('filtered-out');
                }
            });
        }
        
        /**
         * Update stat card active states
         */
        function updateStatCardStates() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                const onclick = card.getAttribute('onclick');
                if (onclick && onclick.includes('toggleFilter')) {
                    // Extract status from onclick
                    const match = onclick.match(/toggleFilter\('([^']+)'\)/);
                    if (match) {
                        const status = match[1];
                        card.classList.toggle('active', activeFilters.has(status));
                    }
                }
            });
        }
        
        // Close modal on backdrop click
        document.getElementById('registerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        document.getElementById('inventoryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeInventoryModal();
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeInventoryModal();
            }
        });
        
        // Load dashboard on page load
        document.addEventListener('DOMContentLoaded', function () {
            clearStoredAuthToken();
            loadDashboard();
        });
    </script>
    <?php endif; ?>
</body>
</html>
