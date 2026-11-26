<?php use VoiceShop\Http\Csrf; ?>
<h1>Set up the demo</h1><p>Create missing tables, sync shop configs and import four fictional orders. Repeating this does not duplicate the seed orders or reset edits.</p><form method="post"><input type="hidden" name="csrf" value="<?= $e(Csrf::token()) ?>"><button>Create tables &amp; seed demo data</button></form><p><a href="index.php">Open dashboard →</a></p>
