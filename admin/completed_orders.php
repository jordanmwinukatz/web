<?php
// Legacy completed_orders.php has been deprecated in favor of the unified submissions_dashboard.php
// Redirect users seamlessly to the completed tab of the new dashboard which has proper styling.
header('Location: submissions_dashboard.php?status=completed');
exit;
