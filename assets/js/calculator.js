// KINAS VOLT - Solar Calculator
class SolarCalculator {
    constructor() {
        this.initCalculator();
    }
    
    initCalculator() {
        const form = document.getElementById('solar-calculator-form');
        if (!form) return;
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.calculate(form);
        });
        
        // Real-time updates
        const inputs = form.querySelectorAll('input[type="range"], input[type="number"]');
        inputs.forEach(input => {
            input.addEventListener('input', () => this.calculate(form));
        });
    }
    
    calculate(form) {
        const data = {
            monthlyBill: parseFloat(form.querySelector('[name="monthly_bill"]').value) || 200,
            roofArea: parseFloat(form.querySelector('[name="roof_area"]').value) || 1500,
            sunHours: parseFloat(form.querySelector('[name="sun_hours"]').value) || 5,
            electricityRate: parseFloat(form.querySelector('[name="electricity_rate"]').value) || 0.12,
            panelEfficiency: parseFloat(form.querySelector('[name="panel_efficiency"]').value) || 20,
            systemLosses: 0.14, // 14% system losses
            degradationRate: 0.005, // 0.5% annual degradation
        };
        
        const results = this.performCalculation(data);
        this.displayResults(results);
    }
    
    performCalculation(data) {
        // Monthly energy consumption in kWh
        const monthlyConsumption = data.monthlyBill / data.electricityRate;
        
        // Daily energy requirement
        const dailyConsumption = monthlyConsumption / 30;
        
        // Required system size considering losses and efficiency
        const systemSizeKW = dailyConsumption / (data.sunHours * (1 - data.systemLosses));
        const adjustedSystemSize = systemSizeKW * (data.panelEfficiency / 100);
        
        // Number of panels (assuming 400W panels)
        const panelWattage = 0.4; // 400W
        const numberOfPanels = Math.ceil((adjustedSystemSize * 1000) / (panelWattage * 1000));
        
        // System cost (average $2.50 per watt)
        const costPerWatt = 2.50;
        const totalSystemCost = adjustedSystemSize * 1000 * costPerWatt;
        
        // Federal tax credit (30%)
        const federalTaxCredit = totalSystemCost * 0.30;
        const netSystemCost = totalSystemCost - federalTaxCredit;
        
        // Monthly savings
        const monthlySavings = data.monthlyBill * 0.85; // 85% offset
        
        // Annual savings
        const annualSavings = monthlySavings * 12;
        
        // Payback period
        const paybackPeriod = netSystemCost / annualSavings;
        
        // 20-year savings with degradation
        let total20YearSavings = 0;
        for (let year = 0; year < 20; year++) {
            const degradationFactor = Math.pow(1 - data.degradationRate, year);
            total20YearSavings += annualSavings * degradationFactor;
        }
        const net20YearSavings = total20YearSavings - netSystemCost;
        
        // CO2 reduction (average 0.92 lbs CO2 per kWh)
        const annualCO2Reduction = (monthlyConsumption * 12 * 0.92) / 2000; // in tons
        
        return {
            systemSizeKW: adjustedSystemSize.toFixed(2),
            numberOfPanels,
            totalSystemCost: totalSystemCost.toFixed(2),
            federalTaxCredit: federalTaxCredit.toFixed(2),
            netSystemCost: netSystemCost.toFixed(2),
            monthlySavings: monthlySavings.toFixed(2),
            annualSavings: annualSavings.toFixed(2),
            paybackPeriod: paybackPeriod.toFixed(1),
            total20YearSavings: total20YearSavings.toFixed(2),
            net20YearSavings: net20YearSavings.toFixed(2),
            annualCO2Reduction: annualCO2Reduction.toFixed(2),
            energyOffset: 85,
        };
    }
    
    displayResults(results) {
        const resultsDiv = document.getElementById('calculator-results');
        if (!resultsDiv) return;
        
        resultsDiv.innerHTML = `
            <div class="calculator-results">
                <h3 class="section-title">Your Solar Estimate</h3>
                
                <div class="result-grid">
                    <div class="result-item highlight">
                        <p class="value">${results.systemSizeKW} kW</p>
                        <p class="label">Recommended System Size</p>
                    </div>
                    
                    <div class="result-item highlight">
                        <p class="value">${results.numberOfPanels}</p>
                        <p class="label">Panels Required (400W)</p>
                    </div>
                    
                    <div class="result-item">
                        <p class="value">$${parseInt(results.totalSystemCost).toLocaleString()}</p>
                        <p class="label">Total System Cost</p>
                    </div>
                    
                    <div class="result-item">
                        <p class="value text-green">-$${parseInt(results.federalTaxCredit).toLocaleString()}</p>
                        <p class="label">Federal Tax Credit (30%)</p>
                    </div>
                    
                    <div class="result-item highlight">
                        <p class="value">$${parseInt(results.netSystemCost).toLocaleString()}</p>
                        <p class="label">Net System Cost</p>
                    </div>
                    
                    <div class="result-item">
                        <p class="value text-green">$${parseInt(results.monthlySavings).toLocaleString()}/mo</p>
                        <p class="label">Estimated Monthly Savings</p>
                    </div>
                    
                    <div class="result-item">
                        <p class="value">${results.paybackPeriod} years</p>
                        <p class="label">Payback Period</p>
                    </div>
                    
                    <div class="result-item highlight">
                        <p class="value text-green">$${parseInt(results.net20YearSavings).toLocaleString()}</p>
                        <p class="label">20-Year Net Savings</p>
                    </div>
                    
                    <div class="result-item">
                        <p class="value">${results.annualCO2Reduction} tons</p>
                        <p class="label">Annual CO₂ Reduction</p>
                    </div>
                    
                    <div class="result-item">
                        <p class="value">${results.energyOffset}%</p>
                        <p class="label">Energy Offset</p>
                    </div>
                </div>
                
                <div class="results-actions">
                    <button onclick="window.print()" class="je2-button green">
                        🖨️ Print / Download Report
                    </button>
                    <button onclick="saveCalculation()" class="je2-button">
                        💾 Save Calculation
                    </button>
                    <button onclick="getQuote()" class="je2-button black">
                        📋 Get Installation Quote
                    </button>
                </div>
            </div>
        `;
        
        resultsDiv.style.display = 'block';
        resultsDiv.scrollIntoView({ behavior: 'smooth' });
    }
}

// Initialize calculator
if (document.getElementById('solar-calculator-form')) {
    new SolarCalculator();
}

async function saveCalculation() {
    try {
        await api.request('calculator/calculate.php', {
            method: 'POST',
            body: JSON.stringify({ /* calculation data */ })
        });
        alert('Calculation saved to your account!');
    } catch (error) {
        alert('Please log in to save calculations');
    }
}

async function getQuote() {
    window.location.href = '/divisions/kinas-volt/installation.php?quote=true';
}