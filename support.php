<?php require 'config.php';auth();$id=$_SESSION['user_id'];$msg='';if($_SERVER['REQUEST_METHOD']=='POST'){$q=$db->prepare("INSERT INTO tickets(user_id,subject,message) VALUES(?,?,?)");$q->bind_param("iss",$id,$_POST['subject'],$_POST['message']);$q->execute();$msg="Ticket created.";}$tickets=$db->query("SELECT * FROM tickets WHERE user_id=$id ORDER BY id DESC");?><!doctype html><html><head>
<?php include __DIR__ . "/theme_init.php"; ?>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Support</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="app"><aside><a class="brand" href="dashboard.php">NWD <span>PANEL</span></a><nav><a href="dashboard.php">Dashboard</a><a href="order.php">New Order</a><a href="services.php">Services</a><a href="orders.php">Orders</a><a href="wallet.php">Wallet</a><a class="active" href="support.php">Support</a><a href="blog.php">Blog</a></nav><a class="logout" href="logout.php">Logout</a></aside><main class="main"><p class="eyebrow">HELP CENTER</p><h1>Support</h1><div class="grid2"><div class="card" style="grid-column:1/-1;">
<h2>Panel Guidelines</h2>
<div style="line-height:1.8; color:var(--muted); font-size:14px;">

<p><strong>1. Order Karne Se Pehle:</strong> Apna link check kar lein ke wo bilkul sahi aur public/open hai (private account par order fail ho sakta hai). Galat link se aane wale order ka refund nahi hoga.</p>

<p><strong>2. Delivery Time:</strong> Order place hote hi provider ko bhej diya jata hai. Zyada tar orders kuch minute se le kar kuch ghanton mein start ho jate hain, lekin high-demand services mein zyada waqt bhi lag sakta hai. [Yahan apni actual average delivery time likh dein]</p>

<p><strong>3. Order Cancel/Refund:</strong> Ek dafa order "Processing" mein chala jaye to usay cancel nahi kiya ja sakta. Agar order "Pending" mein hai to Support ticket bana kar cancel/refund ki request kar sakte hain.</p>

<p><strong>4. Drop/Refill Policy:</strong> Agar delivery ke baad followers/likes kam ho jayein (drop), Support ticket bana kar refill ki request karein, order ID zaroor likhein. [Yahan apni refill guarantee period likh dein, jaise 30 din]</p>

<p><strong>5. Payment/Deposit:</strong> Deposit submit karte waqt sahi Transaction ID (11-20 digits) daalein. Verification mein aam tor par 5 se 30 minute lagte hain. Galat/jaali transaction ID submit karna account suspend karne ki wajah ban sakta hai.</p>

<p><strong>6. Support:</strong> Kisi bhi masle ke liye Support ticket banayein (upar form se), ya WhatsApp icon (neeche-right corner) se seedha chat karein.</p>

<p><strong>7. Account Security:</strong> Apna password kisi ke sath share na karein. Agar aapko koi mashkook (suspicious) activity apne account mein nazar aaye, foran Support ko inform karein.</p>

</div>
</div>
<div class="card"><?php if($msg):?><div class="success"><?=$msg?></div><?php endif;?><form method="post"><label>Subject<input name="subject" required></label><label>Message<textarea name="message" rows="6" required></textarea></label><button class="btn primary">Create Ticket</button></form></div><div class="card"><h2>Your Tickets</h2><?php while($t=$tickets->fetch_assoc()):?><div class="ticket"><b>#<?=$t['id']?> — <?=e($t['subject'])?></b><span class="pill"><?=e($t['status'])?></span><p><?=e($t['message'])?></p></div><?php endwhile;?></div></div></main></div><?php include __DIR__ . "/whatsapp_button.php"; ?>
<?php include __DIR__ . "/theme_toggle.php"; ?>
</body></html>