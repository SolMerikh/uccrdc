  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    window.UCCRDCPortal = { csrf: "<?= htmlspecialchars(csrf_token()) ?>" };
  </script>
  <script src="/uccrdc/assets/js/app.js?v=<?= (int)(@filemtime(__DIR__ . '/../assets/js/app.js') ?: 0) ?>"></script>
</body>
</html>
