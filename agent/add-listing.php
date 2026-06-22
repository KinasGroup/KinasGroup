<!-- AUTOMOBILE DETAILS SECTION -->
<div id="automobileFields" style="display:none; margin-top:24px;">
    <h3 style="margin-bottom:16px;"><i class="fas fa-car"></i> Automobile Details</h3>
    <div class="automobile-fields-grid">
        <!-- Make (Brand) - REQUIRED -->
        <div class="form-group"><label><i class="fas fa-tag"></i> Make *</label>
            <select name="brand" required>...</select>
        </div>
        <!-- Model - REQUIRED -->
        <div class="form-group"><label><i class="fas fa-car"></i> Model *</label>
            <input type="text" name="model" placeholder="e.g., S-Class" required>
        </div>
        <!-- Year - REQUIRED -->
        <div class="form-group"><label><i class="fas fa-calendar"></i> Year *</label>
            <input type="number" name="year" placeholder="e.g., 2018" required>
        </div>
        <!-- Mileage - REQUIRED -->
        <div class="form-group"><label><i class="fas fa-tachometer-alt"></i> Mileage *</label>
            <input type="text" name="mileage" placeholder="e.g., 19592 mi (31530 km)" required>
        </div>
        <!-- Engine - OPTIONAL (No asterisk, no required attribute) -->
        <div class="form-group"><label><i class="fas fa-cog"></i> Engine</label>
            <input type="text" name="engine" placeholder="e.g., 6 Cylinder">
        </div>
        <!-- Gearbox - OPTIONAL -->
        <div class="form-group"><label><i class="fas fa-cogs"></i> Gearbox / Transmission</label>
            <select name="gearbox">...</select>
        </div>
        <!-- Car Type - OPTIONAL -->
        <div class="form-group"><label><i class="fas fa-car-side"></i> Car Type</label>
            <select name="car_type">...</select>
        </div>
        <!-- Drive - OPTIONAL -->
        <div class="form-group"><label><i class="fas fa-steering-wheel"></i> Drive</label>
            <select name="drive">...</select>
        </div>
        <!-- Drive Train - OPTIONAL -->
        <div class="form-group"><label><i class="fas fa-road"></i> Drive Train</label>
            <select name="drive_train">...</select>
        </div>
        <!-- Fuel Type - OPTIONAL -->
        <div class="form-group"><label><i class="fas fa-gas-pump"></i> Fuel Type</label>
            <select name="fuel_type">...</select>
        </div>
        <!-- Condition - OPTIONAL -->
        <div class="form-group"><label><i class="fas fa-clipboard-check"></i> Condition</label>
            <select name="condition">...</select>
        </div>
        <!-- VIN - OPTIONAL -->
        <div class="form-group"><label><i class="fas fa-barcode"></i> VIN</label>
            <input type="text" name="vin" placeholder="e.g., 19UNC1B01JY000027">
        </div>
        <!-- Color - OPTIONAL -->
        <div class="form-group"><label><i class="fas fa-palette"></i> Color</label>
            <input type="text" name="color" placeholder="e.g., Silver">
        </div>
        <!-- Interior Color - OPTIONAL -->
        <div class="form-group"><label><i class="fas fa-palette"></i> Interior Color</label>
            <input type="text" name="interior_color" placeholder="e.g., Grey">
        </div>
        <!-- Doors - OPTIONAL -->
        <div class="form-group"><label><i class="fas fa-door-open"></i> Doors</label>
            <select name="doors">...</select>
        </div>
        <!-- Seats - OPTIONAL -->
        <div class="form-group"><label><i class="fas fa-users"></i> Seats</label>
            <select name="seats">...</select>
        </div>
        <!-- Features - OPTIONAL -->
        <div class="form-group full-width"><label><i class="fas fa-check-circle"></i> Features (comma separated)</label>
            <input type="text" name="features" placeholder="e.g., Leather seats, Sunroof, Navigation, Backup camera">
        </div>
    </div>
</div>
