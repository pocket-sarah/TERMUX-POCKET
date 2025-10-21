<?php
session_start();

// Example rewards data
$rewards = $_SESSION['rewards'] ?? [
    ['title' => 'Cashback Rewards', 'icon' => 'fa-money-bill-wave', 'description' => 'You’ve earned $45.20 this month!', 'link' => 'cashback_details.php'],
    ['title' => 'Referral Bonus', 'icon' => 'fa-user-friends', 'description' => 'Invite friends and earn $10 per referral!', 'link' => 'referral_program.php'],
    ['title' => 'Invite Member', 'icon' => 'fa-user-plus', 'description' => 'Send an invitation to a new member and grow your network.', 'link' => 'invite_member.php'],
    ['title' => 'Loyalty Points', 'icon' => 'fa-gem', 'description' => 'You have accumulated 1,250 points so far!', 'link' => 'loyalty_points.php'],
    ['title' => 'Birthday Bonus', 'icon' => 'fa-birthday-cake', 'description' => 'Celebrate your birthday with a special $15 reward!', 'link' => 'birthday_bonus.php'],
    ['title' => 'Milestone Reward', 'icon' => 'fa-trophy', 'description' => 'Congrats! You’ve reached Level 5 and earned a bonus.', 'link' => 'milestone_rewards.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rewards Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --primary:#1d123c;
  --secondary:#c5e600;
  --background:#2a1852;
  --text:#ffffff;
}
body {
  font-family: 'Poppins', sans-serif;
  background: var(--background);
  color: var(--text);
  margin: 0;
  padding: 0;
}
.page-title {
  text-align: center;
  font-size: 1.4rem;
  font-weight: 700;
  padding: 15px;
  background: var(--primary);
  color: var(--secondary);
  border-bottom: 2px solid var(--secondary);
  text-transform: uppercase;
  letter-spacing: .8px;
}
.rewards-section {
  padding: 12px;
}
.reward-card {
  background: var(--primary);
  padding: 16px;
  margin: 10px 0;
  border-radius: 10px;
  font-size: 0.85rem;
  transition: background 0.2s;
  cursor: pointer;
  display: flex;
  align-items: center; /* ensures vertical alignment */
}
.reward-card:hover {
  background: #36246a;
}
.icon-container {
  flex: 0 0 40px; /* fixed width for icon */
  text-align: center;
  margin-right: 12px;
}
.icon-container i {
  font-size: 1.5rem;
  color: var(--secondary);
}
.reward-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.reward-info .title {
  font-weight: 600;
  color: var(--secondary);
  margin-bottom: 3px;
  font-size: 0.95rem;
}
.reward-info .desc {
  font-size: 0.8rem;
  opacity: 0.9;
  line-height: 1.2;
  word-break: break-word;
}
</style>
</head>
<body>


<div class="rewards-section">
  <?php foreach($rewards as $reward): ?>
    <div class="reward-card" onclick="location.href='<?= htmlspecialchars($reward['link']) ?>'">
      <div class="icon-container"><i class="fa-solid <?= htmlspecialchars($reward['icon']) ?>"></i></div>
      <div class="reward-info">
        <div class="title"><?= htmlspecialchars($reward['title']) ?></div>
        <div class="desc"><?= htmlspecialchars($reward['description']) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

</body>
</html>