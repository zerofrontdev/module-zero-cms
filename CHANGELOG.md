# Changelog: zerofront/module-zero-cms

## 1.3.6

- **Reset** in the editor refreshes the page in the full page cache, as Save does.
- Every editor save or removal cleans the page, block or post in the page cache (built-in or Varnish) and the block cache.
- Array-valued parameters in the preview no longer cause a 500.

Earlier releases (1.3.5 and before): see the Git tags.
