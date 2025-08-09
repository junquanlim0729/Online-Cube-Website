<footer id="custFooter" style="background:#f8f9fa; border-top:1px solid #e0e0e0; padding:12px 20px; text-align:center; color:#555; position:fixed; left:0; right:0; bottom:0; transform: translateY(100%); transition: transform 0.25s ease; z-index: 999;">
    <div style="max-width:1200px; margin:0 auto;">
        <small>&copy; <?php echo date('Y'); ?> CubePro Hub. All rights reserved.</small>
    </div>
</footer>
<script>
(function(){
  const footer = document.getElementById('custFooter');
  let lastY = window.pageYOffset || document.documentElement.scrollTop;
  function nearBottom(){
    const scrollY = window.pageYOffset || document.documentElement.scrollTop;
    const viewport = window.innerHeight || document.documentElement.clientHeight;
    const docH = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
    return (scrollY + viewport) >= (docH - 40);
  }
  window.addEventListener('scroll', function(){
    const y = window.pageYOffset || document.documentElement.scrollTop;
    if (nearBottom()) {
      footer.style.transform = 'translateY(0)';
    } else if (y < lastY) { // scrolling up, hide footer
      footer.style.transform = 'translateY(100%)';
    }
    lastY = y;
  }, { passive: true });
})();
</script>


