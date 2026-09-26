// Handle select all checkboxes
document.addEventListener('DOMContentLoaded', function() {
    const selectAll1 = document.getElementById('cb-select-all-1');
    const selectAll2 = document.getElementById('cb-select-all-2');
    const checkboxes = document.querySelectorAll('input[name="reviews[]"]');
    
    function toggleAll(checked) {
        checkboxes.forEach(cb => cb.checked = checked);
        if (selectAll1) selectAll1.checked = checked;
        if (selectAll2) selectAll2.checked = checked;
    }
    
    if (selectAll1) {
        selectAll1.addEventListener('change', function() {
            toggleAll(this.checked);
        });
    }
    
    if (selectAll2) {
        selectAll2.addEventListener('change', function() {
            toggleAll(this.checked);
        });
    }
    
    // Update select all state based on individual checkboxes
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('input[name="reviews[]"]:checked').length;
            const allChecked = checkedCount === checkboxes.length;
            const someChecked = checkedCount > 0;
            
            if (selectAll1) {
                selectAll1.checked = allChecked;
                selectAll1.indeterminate = someChecked && !allChecked;
            }
            if (selectAll2) {
                selectAll2.checked = allChecked;
                selectAll2.indeterminate = someChecked && !allChecked;
            }
        });
    });
});
