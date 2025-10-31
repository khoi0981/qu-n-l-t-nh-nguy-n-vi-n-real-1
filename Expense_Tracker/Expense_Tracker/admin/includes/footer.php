<?php
// filepath: env-protection-admin/env-protection-admin/includes/footer.php
?>
<style>
/* Footer simplified: do not change global layout (avoid forcing body flex) */
html, body { margin: 0; }

/* footer styles */
#site-footer {
    position: relative;
    left: 0;
    right: 0;
    background: #16a085;
    color: #fff;
    padding: 12px 0;
    text-align: center;
    box-shadow: 0 -4px 10px rgba(0,0,0,0.08);
}
#site-footer .container { max-width:1100px; margin:0 auto; padding:0 16px; background:transparent; }
#site-footer a { color: #fff; text-decoration: none; }
</style>

<footer id="site-footer" role="contentinfo" aria-label="Footer">
    <div class="container">
        <p style="margin:4px 0;font-size:13px">&copy; <?php echo date("Y"); ?> Environmental Protection Project. All rights reserved.</p>
        <ul style="list-style: none; padding: 0; margin:6px 0;">
            <li style="display: inline; margin: 0 10px;"><a href="privacy_policy.php">Privacy Policy</a></li>
            <li style="display: inline; margin: 0 10px;"><a href="terms_of_service.php">Terms of Service</a></li>
            <li style="display: inline; margin: 0 10px;"><a href="contact.php">Contact Us</a></li>
        </ul>
    </div>
</footer>

<!-- No footer JS needed - keep footer in normal document flow -->