<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
include '../../templates/header.php';
?>

<div style="max-width: 800px; margin: 100px auto; padding: 20px;">
    <h1 style="color: red;">JavaScript Test Page</h1>
    <p>If you see this, PHP is working.</p>
    
    <!-- TEST BUTTON 1: Simple inline onclick -->
    <button onclick="alert('Inline alert works!')" style="padding: 10px 20px; background: green; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 10px;">
        Test 1: Inline Alert
    </button>
    
    <!-- TEST BUTTON 2: Calls a function defined below -->
    <button onclick="testFunction()" style="padding: 10px 20px; background: blue; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 10px;">
        Test 2: Function Call
    </button>
    
    <!-- TEST BUTTON 3: Logs to console -->
    <button onclick="console.log('Console log from button!')" style="padding: 10px 20px; background: orange; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 10px;">
        Test 3: Console Log
    </button>
</div>

<script>
// This function is defined at the top level
function testFunction() {
    alert('Function call works!');
    console.log('Function was called!');
}

// This runs immediately when the script loads
console.log('=== TEST PAGE JAVASCRIPT LOADED ===');
console.log('Script is executing!');

// This runs when the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DOM IS FULLY LOADED ===');
    alert('Page fully loaded! JavaScript is working!');
});
</script>

<?php include '../../templates/footer.php'; ?>
