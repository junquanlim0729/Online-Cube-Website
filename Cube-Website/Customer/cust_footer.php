<footer id="custFooter" style="background:#1f1f1f; color:#fff; border-top:1px solid #2a2a2a; position:fixed; left:0; right:0; bottom:0; transform: translateY(100%); transition: transform 0.28s ease-in-out; z-index: 999;">
  <div style="width:100%; padding:26px 20px;">
    <div style="display:flex; gap:28px; flex-wrap:wrap; align-items:flex-start;">
      <div style="min-width:160px; flex:1;">
        <div style="font-weight:700; letter-spacing:.3px; margin-bottom:12px;">LET US HELP</div>
        <ul style="list-style:none; padding:0; margin:0; line-height:1.9; color:#cfcfcf;">
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Order Editing</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Contact Us</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Help Center</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Returns & Exchanges</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Shipping & Delivery</a></li>
        </ul>
      </div>
      <div style="min-width:160px; flex:1;">
        <div style="font-weight:700; letter-spacing:.3px; margin-bottom:12px;">INFORMATION</div>
        <ul style="list-style:none; padding:0; margin:0; line-height:1.9; color:#cfcfcf;">
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Wishlist</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Rewards</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Gift Cards</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Showroom</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Wallpapers & Logos</a></li>
        </ul>
      </div>
      <div style="min-width:160px; flex:1;">
        <div style="font-weight:700; letter-spacing:.3px; margin-bottom:12px;">LEARN</div>
        <ul style="list-style:none; padding:0; margin:0; line-height:1.9; color:#cfcfcf;">
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Parents</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Rubik's Cube Tutorial</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Getting Started</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Lubrication</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Community</a></li>
        </ul>
      </div>
      <div style="min-width:160px; flex:1;">
        <div style="font-weight:700; letter-spacing:.3px; margin-bottom:12px;">ABOUT US</div>
        <ul style="list-style:none; padding:0; margin:0; line-height:1.9; color:#cfcfcf;">
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Our Story</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Reviews</a></li>
          <li><a href="#" style="color:#cfcfcf; text-decoration:none;">Newsletter</a></li>
        </ul>
      </div>
      <div style="min-width:240px; flex:1.2;">
        <div style="font-weight:700; letter-spacing:.3px; margin-bottom:12px;">STAY CONNECTED</div>
        <div style="display:flex; gap:10px; margin-bottom:12px;">
          <a href="#" title="Facebook" style="width:34px;height:34px;background:#2b2b2b;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;text-decoration:none;">f</a>
          <a href="#" title="Twitter" style="width:34px;height:34px;background:#2b2b2b;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;text-decoration:none;">t</a>
          <a href="#" title="Instagram" style="width:34px;height:34px;background:#2b2b2b;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;text-decoration:none;">ig</a>
          <a href="#" title="YouTube" style="width:34px;height:34px;background:#2b2b2b;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;text-decoration:none;">▶</a>
          <a href="#" title="TikTok" style="width:34px;height:34px;background:#2b2b2b;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;text-decoration:none;">tt</a>
          <a href="#" title="Pinterest" style="width:34px;height:34px;background:#2b2b2b;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;text-decoration:none;">p</a>
        </div>
        <div style="font-weight:700; letter-spacing:.2px; font-size:12px; color:#cfcfcf; margin-bottom:8px;">GET SALES, NEW RELEASES, TIPS, AND NEWS</div>
        <div style="display:flex; gap:8px;">
          <input type="email" placeholder="Email Address" style="flex:1; min-width:160px; padding:10px 12px; border-radius:4px; border:1px solid #3a3a3a; background:#2b2b2b; color:#eaeaea; outline:none;">
          <button type="button" style="background:#ff7a00; color:#1f1f1f; font-weight:700; border:none; border-radius:4px; padding:10px 14px; cursor:pointer;">Subscribe</button>
        </div>
      </div>
    </div>
    <div style="margin-top:16px; text-align:center; color:#bdbdbd; font-size:12px; width:100%;">&copy; <?php echo date('Y'); ?> CubePro Hub. All rights reserved.</div>
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
    return (scrollY + viewport) >= (docH - 120);
  }
  function isShortPage(){
    const viewport = window.innerHeight || document.documentElement.clientHeight;
    const docH = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
    return docH <= viewport + 1;
  }
  function hide(){ footer.style.transform = 'translateY(100%)'; }
  function show(){ footer.style.transform = 'translateY(0)'; }
  window.addEventListener('scroll', function(){
    const y = window.pageYOffset || document.documentElement.scrollTop;
    const scrollingDown = y > lastY;
    if (!scrollingDown) { hide(); }
    else if (nearBottom()) { show(); } else { hide(); }
    lastY = y;
  }, { passive: true });
  window.addEventListener('wheel', function(e){ if (!isShortPage()) return; if (e.deltaY > 0) show(); else if (e.deltaY < 0) hide(); }, { passive: true });
  let touchStartY = null;
  window.addEventListener('touchstart', function(e){ touchStartY = e.changedTouches && e.changedTouches.length ? e.changedTouches[0].clientY : null; }, { passive: true });
  window.addEventListener('touchmove', function(e){ if (!isShortPage() || touchStartY === null) return; const y = e.changedTouches && e.changedTouches.length ? e.changedTouches[0].clientY : touchStartY; const d = touchStartY - y; if (d > 8) show(); else if (d < -8) hide(); }, { passive: true });
})();
</script>


