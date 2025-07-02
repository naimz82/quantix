// Quantix Inventory Tracker - Main JavaScript File

$(document).ready(function() {
    // Initialize components
    initializeComponents();
    
    // Set up event handlers
    setupEventHandlers();
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
});

function initializeComponents() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Initialize DataTables
    if ($('.data-table').length) {
        $('.data-table').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Format dates
    $('.format-date').each(function() {
        var date = $(this).text();
        if (date) {
            $(this).text(moment(date).format('MMM DD, YYYY'));
        }
    });
    
    $('.format-datetime').each(function() {
        var datetime = $(this).text();
        if (datetime) {
            $(this).text(moment(datetime).format('MMM DD, YYYY HH:mm'));
        }
    });
}

function setupEventHandlers() {
    // Delete confirmation
    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        var itemName = $(this).data('name') || 'this item';
        
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete "${itemName}". This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    });
    
    // Form validation
    $('.needs-validation').on('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });
    
    // Numeric input validation
    $('.numeric-input').on('input', function() {
        this.value = this.value.replace(/[^0-9.]/g, '');
    });
    
    // Auto-calculate stock levels
    $('#quantity, #low_stock_threshold').on('input', function() {
        var quantity = parseInt($('#quantity').val()) || 0;
        var threshold = parseInt($('#low_stock_threshold').val()) || 0;
        
        var statusBadge = $('#stock-status');
        if (quantity <= 0) {
            statusBadge.removeClass().addClass('badge bg-danger').text('Out of Stock');
        } else if (quantity <= threshold) {
            statusBadge.removeClass().addClass('badge bg-warning').text('Low Stock');
        } else {
            statusBadge.removeClass().addClass('badge bg-success').text('In Stock');
        }
    });
}

// Utility functions
function showAlert(message, type = 'success') {
    Swal.fire({
        title: type === 'success' ? 'Success!' : 'Error!',
        text: message,
        icon: type,
        timer: 3000,
        showConfirmButton: false
    });
}

function showLoading(button) {
    var originalText = button.html();
    button.data('original-text', originalText);
    button.html('<span class="loading me-2"></span>Loading...').prop('disabled', true);
}

function hideLoading(button) {
    var originalText = button.data('original-text');
    button.html(originalText).prop('disabled', false);
}

function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

// AJAX helper functions
function makeAjaxRequest(url, data, method = 'POST') {
    return $.ajax({
        url: url,
        method: method,
        data: data,
        dataType: 'json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    });
}

// Stock movement functions
function addStockIn(data) {
    return makeAjaxRequest('/quantix/api/stock-in.php', data);
}

function addStockOut(data) {
    return makeAjaxRequest('/quantix/api/stock-out.php', data);
}

function getItemDetails(itemId) {
    return makeAjaxRequest('/quantix/api/items.php?action=get&id=' + itemId, {}, 'GET');
}

// Export functions
function exportToCSV(tableId, filename = 'export.csv') {
    var csv = [];
    var rows = document.querySelectorAll(tableId + " tr");
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        
        for (var j = 0; j < cols.length; j++) {
            // Get text content and escape quotes
            var cellText = cols[j].innerText.replace(/"/g, '""');
            row.push('"' + cellText + '"');
        }
        
        csv.push(row.join(","));
    }
    
    // Download CSV file
    var csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
    var downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Print function
function printPage(elementId = null) {
    if (elementId) {
        var printContent = document.getElementById(elementId).innerHTML;
        var originalContent = document.body.innerHTML;
        document.body.innerHTML = printContent;
        window.print();
        document.body.innerHTML = originalContent;
        location.reload();
    } else {
        window.print();
    }
}

// Dashboard specific functions
function updateDashboardStats() {
    makeAjaxRequest('/quantix/api/dashboard.php', {action: 'stats'}, 'GET')
        .done(function(response) {
            if (response.success) {
                $('#total-items').text(formatNumber(response.data.total_items));
                $('#low-stock-count').text(formatNumber(response.data.low_stock_count));
                $('#total-categories').text(formatNumber(response.data.total_categories));
                $('#total-suppliers').text(formatNumber(response.data.total_suppliers));
            }
        })
        .fail(function() {
            console.error('Failed to update dashboard stats');
        });
}

// Chart functions
function createStockChart(canvasId, data) {
    var ctx = document.getElementById(canvasId).getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['In Stock', 'Low Stock', 'Out of Stock'],
            datasets: [{
                data: [data.in_stock, data.low_stock, data.out_of_stock],
                backgroundColor: ['#198754', '#ffc107', '#dc3545'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Auto-refresh functions
function startAutoRefresh(callback, interval = 30000) {
    setInterval(callback, interval);
}

// Notification functions
function checkLowStockAlerts() {
    makeAjaxRequest('/quantix/api/alerts.php', {action: 'low_stock'}, 'GET')
        .done(function(response) {
            if (response.success && response.data.length > 0) {
                var message = `You have ${response.data.length} item(s) with low stock levels.`;
                showAlert(message, 'warning');
            }
        });
}

// Helper functions
function formatNumber(number) {
    return new Intl.NumberFormat().format(number);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Search functionality
function setupSearchFilters() {
    $('.search-input').on('input', debounce(function() {
        var searchTerm = $(this).val().toLowerCase();
        var targetTable = $(this).data('target');
        
        $(targetTable + ' tbody tr').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(searchTerm) > -1);
        });
    }, 300));
}

// Form validation
function validateStockForm(form) {
    var isValid = true;
    var errors = [];
    
    // Check required fields
    form.find('[required]').each(function() {
        if (!$(this).val()) {
            isValid = false;
            errors.push($(this).prev('label').text() + ' is required');
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
    
    // Check numeric fields
    form.find('.numeric-input').each(function() {
        var value = parseFloat($(this).val());
        if (isNaN(value) || value < 0) {
            isValid = false;
            errors.push($(this).prev('label').text() + ' must be a valid positive number');
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
    
    if (!isValid) {
        showAlert(errors.join('<br>'), 'danger');
    }
    
    return isValid;
}

// Enhanced data loading
function loadData(url, target, template) {
    $(target).html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
    
    makeAjaxRequest(url, {}, 'GET')
        .done(function(response) {
            if (response.success) {
                var html = '';
                response.data.forEach(function(item) {
                    html += template(item);
                });
                $(target).html(html);
            } else {
                $(target).html('<div class="alert alert-danger">Failed to load data</div>');
            }
        })
        .fail(function() {
            $(target).html('<div class="alert alert-danger">Error loading data</div>');
        });
}

// Initialize auto-refresh for dashboard
if (window.location.pathname.includes('dashboard.php')) {
    startAutoRefresh(function() {
        updateDashboardStats();
        checkLowStockAlerts();
    }, 60000); // Refresh every minute
}

// Initialize search filters
$(document).ready(function() {
    setupSearchFilters();
});
