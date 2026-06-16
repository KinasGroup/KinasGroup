// assets/js/solar-calculator.js
const defaultAppliances = [
    { name: "LED Bulbs", watt: 10 },
    { name: "Ceiling Fan", watt: 60 },
    { name: "Standing Fan", watt: 50 },
    { name: "Television (LED)", watt: 120 },
    { name: "DSTV Decoder", watt: 30 },
    { name: "Refrigerator", watt: 150 },
    { name: "Deep Freezer", watt: 250 },
    { name: "Air Conditioner 1HP", watt: 800 },
    { name: "Air Conditioner 1.5HP", watt: 1200 },
    { name: "Air Conditioner 2HP", watt: 1800 },
    { name: "Washing Machine", watt: 500 },
    { name: "Microwave", watt: 1000 },
    { name: "Electric Kettle", watt: 1500 },
    { name: "Pressing Iron", watt: 1000 },
    { name: "Water Dispenser", watt: 500 },
    { name: "Borehole Pump (1HP)", watt: 750 },
    { name: "CCTV System", watt: 50 },
    { name: "Wi-Fi Router", watt: 20 },
    { name: "Starlink Dish", watt: 50 },
    { name: "Desktop Computer", watt: 300 },
    { name: "Laptop", watt: 65 },
    { name: "Printer", watt: 50 }
];

let applianceCounter = 0;

document.addEventListener('DOMContentLoaded', function() {
    loadDefaultAppliances();
    updateTotals();
});

function loadDefaultAppliances() {
    const container = document.getElementById('appliance-list');
    container.innerHTML = '';

    defaultAppliances.forEach(appl => {
        addApplianceRow(appl.name, appl.watt, 1, 4);
    });
}

function addApplianceRow(name, watt, qty = 1, hours = 4) {
    const container = document.getElementById('appliance-list');
    const id = `appl_${applianceCounter++}`;

    const row = document.createElement('div');
    row.className = 'appliance-row';
    row.id = id;
    row.innerHTML = `
        <div>
            <input type="text" name="appliance_name[]" value="${name}" class="appl-name" required>
        </div>
        <div>
            <input type="number" name="appliance_watt[]" value="${watt}" min="1" class="appl-watt" required>
            <small>Watts</small>
        </div>
        <div>
            <input type="number" name="appliance_qty[]" value="${qty}" min="1" class="appl-qty" required>
            <small>Qty</small>
        </div>
        <div>
            <input type="number" name="appliance_hours[]" value="${hours}" min="0.5" step="0.5" class="appl-hours" required>
            <small>Hours/day</small>
        </div>
        <div>
            <button type="button" class="remove-btn" onclick="removeAppliance('${id}')">×</button>
        </div>
    `;

    container.appendChild(row);
    updateTotals();
}

function addCustomAppliance() {
    addApplianceRow("Custom Appliance", 100, 1, 4);
}

function removeAppliance(id) {
    const row = document.getElementById(id);
    if (row) row.remove();
    updateTotals();
}

function updateTotals() {
    let totalWatts = 0;
    let dailyWh = 0;

    document.querySelectorAll('.appliance-row').forEach(row => {
        const watt = parseFloat(row.querySelector('.appl-watt').value) || 0;
        const qty  = parseFloat(row.querySelector('.appl-qty').value) || 1;
        const hours = parseFloat(row.querySelector('.appl-hours').value) || 0;

        totalWatts += watt * qty;
        dailyWh += watt * qty * hours;
    });

    const dailyKwh = (dailyWh / 1000).toFixed(2);

    // Update summary if elements exist
    const peakEl = document.getElementById('peak-load');
    const dailyEl = document.getElementById('daily-kwh');
    
    if (peakEl) peakEl.textContent = totalWatts.toLocaleString();
    if (dailyEl) dailyEl.textContent = dailyKwh;
}

function nextStep(step) {
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    document.getElementById(`step${step}`).classList.add('active');
    
    if (step === 4) {
        updateTotals();
    }
}

// Real-time updates
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('appl-watt') || 
        e.target.classList.contains('appl-qty') || 
        e.target.classList.contains('appl-hours')) {
        updateTotals();
    }
});

// Form submission handling
document.getElementById('solarCalculatorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/api/solar/calculate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`✅ Proposal generated successfully!\nReference: ${data.reference}\n\nCheck your email for the PDF.`);
            // Optionally redirect or show success screen
            window.location.href = `/divisions/kinas-volt/calculator.php?success=1&ref=${data.reference}`;
        } else {
            alert('Error: ' + (data.message || 'Failed to generate proposal'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Connection error. Please try again.');
    });
});
