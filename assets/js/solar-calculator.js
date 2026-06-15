const appliances = [
    {name: "LED Bulbs", watt: 10},
    {name: "Ceiling Fan", watt: 60},
    {name: "Standing Fan", watt: 50},
    {name: "Television", watt: 120},
    {name: "Refrigerator", watt: 150},
    {name: "Air Conditioner 1.5HP", watt: 1200},
    // ... more items
];

function nextStep(n) {
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    document.getElementById('step'+n).classList.add('active');
}

function addCustomAppliance() {
    // Dynamic row addition logic
    alert("Custom appliance added (demo)");
}
