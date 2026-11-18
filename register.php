<?php
/**
 * Register Maintenance Activity Page
 * Form to log maintenance activities
 */

// Load environment configuration
$config = require __DIR__ . '/env.php';
require_once __DIR__ . '/auth.php';

if (!isAuthenticated($config)) {
    header('Location: index.php');
    exit;
}

// Set timezone to GMT+1 (Amsterdam/Brussels)
date_default_timezone_set('Europe/Amsterdam');

// Get current date and time for default values
$currentDate = date('Y-m-d');
$currentTime = date('H:i');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2d6b7a">
    <?php require __DIR__ . '/pwa-head.php'; ?>
    <title>Onderhoud Registreren</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .back-btn {
            display: inline-block;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .back-btn:hover {
            text-decoration: underline;
        }
        
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-group small {
            display: block;
            color: #666;
            margin-top: 5px;
            font-size: 13px;
        }
        
        .datetime-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .activity-info {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            display: none;
        }
        
        .activity-info.active {
            display: block;
        }
        
        .activity-info .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .activity-info .info-row:last-child {
            margin-bottom: 0;
        }
        
        .activity-info .label {
            font-weight: 600;
            color: #555;
        }
        
        .activity-info .value {
            color: #333;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }
        
        .alert.active {
            display: block;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .stock-warning {
            background: #fff3e0;
            border: 1px solid #ffb74d;
            color: #e65100;
            padding: 12px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 14px;
            display: none;
        }
        
        .stock-warning.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .datetime-group {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="index.php" class="back-btn">← Terug naar Dashboard</a>
            <h1>📝 Onderhoud Registreren</h1>
            <p>Log een uitgevoerde onderhoudsactiviteit</p>
        </div>
        
        <div class="form-card">
            <div id="alert" class="alert"></div>
            
            <form id="maintenanceForm">
                <div class="form-group">
                    <label for="activity">Activiteit *</label>
                    <select id="activity" name="activity" required>
                        <option value="">Selecteer een activiteit...</option>
                    </select>
                    <div id="activityInfo" class="activity-info"></div>
                    <div id="stockWarning" class="stock-warning"></div>
                </div>
                
                <div class="datetime-group">
                    <div class="form-group">
                        <label for="date">Datum *</label>
                        <input type="date" id="date" name="date" value="<?= $currentDate ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="time">Tijd</label>
                        <input type="time" id="time" name="time" value="<?= $currentTime ?>">
                        <small>Optioneel - voor nauwkeurige tijdregistratie</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notities</label>
                    <textarea id="notes" name="notes" placeholder="Bijv: Filter vervangen, water was troebel..."></textarea>
                    <small>Optioneel - extra informatie over deze onderhoudsbeurt</small>
                </div>
                
                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
                    <button type="submit" class="btn btn-primary">
                        <span id="submitText">Registreren</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const API_URL = 'api.php';
        let activities = [];
        let inventory = {};
        
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
         * Load activities and inventory
         */
        async function loadData() {
            try {
                const activitySelect = document.getElementById('activity');
                activitySelect.innerHTML = '<option value="">Laden...</option>';
                
                // Fetch activities and inventory
                [activities, inventoryData] = await Promise.all([
                    fetchAPI('activities'),
                    fetchAPI('inventory')
                ]);
                
                // Convert inventory array to object for easy lookup
                inventoryData.forEach(item => {
                    inventory[item.code] = item;
                });
                
                // Populate select
                activitySelect.innerHTML = '<option value="">Selecteer een activiteit...</option>';
                activities.forEach(activity => {
                    const option = document.createElement('option');
                    option.value = activity.code;
                    option.textContent = `${activity.code} - ${activity.description}`;
                    option.dataset.activity = JSON.stringify(activity);
                    activitySelect.appendChild(option);
                });
                
            } catch (error) {
                showAlert('error', 'Fout bij laden van gegevens: ' + error.message);
            }
        }
        
        /**
         * Handle activity selection
         */
        document.getElementById('activity').addEventListener('change', function(e) {
            const selectedOption = e.target.selectedOptions[0];
            const activityInfo = document.getElementById('activityInfo');
            const stockWarning = document.getElementById('stockWarning');
            
            if (!selectedOption.dataset.activity) {
                activityInfo.classList.remove('active');
                stockWarning.classList.remove('active');
                return;
            }
            
            const activity = JSON.parse(selectedOption.dataset.activity);
            
            // Show activity info
            activityInfo.innerHTML = `
                <div class="info-row">
                    <span class="label">Frequentie:</span>
                    <span class="value">${activity.frequency_weeks} weken</span>
                </div>
                <div class="info-row">
                    <span class="label">Type:</span>
                    <span class="value">${activity.is_filter_material ? 'Filter materiaal' : 'Regulier onderhoud'}</span>
                </div>
            `;
            activityInfo.classList.add('active');
            
            // Show stock warning if applicable
            if (activity.is_filter_material && inventory[activity.code]) {
                const stock = inventory[activity.code];
                const currentStock = stock.current_stock;
                
                if (currentStock <= 0) {
                    stockWarning.innerHTML = '⚠️ <strong>Let op:</strong> Geen voorraad meer! Voorraad wordt 0.';
                    stockWarning.classList.add('active');
                } else if (currentStock <= stock.min_stock_alert + 1) {
                    stockWarning.innerHTML = `⚠️ <strong>Let op:</strong> Lage voorraad (${currentStock} stuks). Na registratie: ${currentStock - 1} stuks.`;
                    stockWarning.classList.add('active');
                } else {
                    stockWarning.classList.remove('active');
                }
            } else {
                stockWarning.classList.remove('active');
            }
        });
        
        /**
         * Handle form submission
         */
        document.getElementById('maintenanceForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const submitText = document.getElementById('submitText');
            const alertEl = document.getElementById('alert');
            
            try {
                // Disable submit button
                submitBtn.disabled = true;
                submitText.textContent = 'Bezig...';
                
                // Get form values
                const code = document.getElementById('activity').value;
                const date = document.getElementById('date').value;
                const time = document.getElementById('time').value;
                const notes = document.getElementById('notes').value.trim();
                
                // Combine date and time if time is provided
                let performedDate = date;
                if (time) {
                    performedDate = `${date} ${time}:00`;
                }
                
                // Submit to API
                const result = await fetchAPI('record_maintenance', 'POST', {
                    code: code,
                    date: performedDate,
                    notes: notes || null
                });
                
                // Show success message
                showAlert('success', `✓ Onderhoud succesvol geregistreerd! Over 2 seconden wordt je teruggestuurd naar het dashboard.`);
                
                // Reset form
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 2000);
                
            } catch (error) {
                showAlert('error', 'Fout bij registreren: ' + error.message);
                submitBtn.disabled = false;
                submitText.textContent = 'Registreren';
            }
        });
        
        /**
         * Show alert message
         */
        function showAlert(type, message) {
            const alertEl = document.getElementById('alert');
            alertEl.className = `alert alert-${type} active`;
            alertEl.textContent = message;
            
            // Auto-hide error messages after 5 seconds
            if (type === 'error') {
                setTimeout(() => {
                    alertEl.classList.remove('active');
                }, 5000);
            }
        }
        
        /**
         * Reset form
         */
        function resetForm() {
            document.getElementById('maintenanceForm').reset();
            document.getElementById('activityInfo').classList.remove('active');
            document.getElementById('stockWarning').classList.remove('active');
            document.getElementById('alert').classList.remove('active');
            
            // Reset to current date/time
            document.getElementById('date').value = '<?= $currentDate ?>';
            document.getElementById('time').value = '<?= $currentTime ?>';
        }
        
        // Load data on page load
        document.addEventListener('DOMContentLoaded', loadData);
    </script>
</body>
</html>
