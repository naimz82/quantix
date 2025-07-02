    </main>
    
    <!-- Footer -->
    <?php if (isLoggedIn()): ?>
    <footer class="bg-light border-top py-3 mt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <small class="text-muted">
                        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i>
                        Logged in as: <strong><?php echo $_SESSION['user_name']; ?></strong>
                        (<?php echo ucfirst($_SESSION['user_role']); ?>)
                    </small>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>
    
    <!-- Scripts -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Moment.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.9.0/dist/sweetalert2.all.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>
    
    <?php if (isset($customScript)): ?>
    <script><?php echo $customScript; ?></script>
    <?php endif; ?>
    
</body>
</html>
