<?php
if (!function_exists('ss')) {
    /**
     * Khởi session nếu cần và trả về true khi user đã đăng nhập.
     * Điều chỉnh kiểm tra $_SESSION['user_id'] nếu ứng dụng dùng khóa khác.
     */
    function ss(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['user_id'] ?? null);
    }
}
?>
<style>
      .navbar {
    animation: fadeIn 0.6s ease-out;
    background: rgba(255,255,255,0.98); /* trắng mờ */
    box-shadow: 0 6px 20px rgba(30,60,40,0.06);
    color: #1f6b4d;
    padding: 12px 10px;
    border-radius: 6px;
    transform-origin: top;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}


.nav-item {
    position: relative;
    color: #000; /* Set default text color to black */
    text-decoration: none;
    font-size: 8px;
    border-radius: 5px;
    transition: color 0.3s ease, box-shadow 0.3s ease; /* Add transitions for text color and box-shadow */
}

.nav-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: transparent; /* Set the initial overlay color to transparent */
    border-radius: 5px;
    z-index: -1;
    transition: background-color 0.3s ease; /* Add transition for overlay color */
}

.nav-item:hover::before {
    background-color: #2980b9; /* Change overlay color to blue on hover */
}

.nav-item:hover {
    color: #fff; /* Change text color to white on hover */
}

.nav-item:hover::before {
    background-color: #f0f8ff; /* Change overlay color to black when the mouse gets closer to the text */
}




	.navbar-caption{min-width:450px;
	font-size:39px;}
</style>
<section class="menu menu1 cid-sBOHoABUOH" once="menu" id="menu1-0">

	    <nav class="navbar navbar-dropdown navbar-fixed-top navbar-expand-lg">
		<div class="container">
		    <div class="navbar-brand">

			<span class="navbar-caption-wrap"><a class="navbar-caption text-info display-5" href="index.php" style="font-size: 55px;">Expense Tracker&nbsp;</a></span>
		    </div>
		    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
			<div class="hamburger">
			    <span></span>
			    <span></span>
			    <span></span>
			    <span></span>
		    </div>
		    </button>
		    <div class="collapse navbar-collapse" id="navbarSupportedContent">
			<ul class="navbar-nav nav-dropdown nav-right" data-app-modern-menu="true">
			    
			    <?php
			    if(ss()){
				?><li class="nav-item"><a class="nav-link link text-warning display-7" href="index.php">Dashboard</a></li><?php
			    }
			    ?>
				<li class="nav-item"><a class="nav-link link text-warning display-7" href="manage_expenses.php">Expenses</a></li>
				<li class="nav-item"><a class="nav-link link text-warning display-7" href="manage_categories.php">Categories</a></li>
			    <li class="nav-item"><a class="nav-link link text-warning display-7" href="profile.php">My Profile</a></li>
			    <li class="nav-item"><a class="nav-link link text-warning display-7" href="income.php">Income</a></li>
				<li class="nav-item"><a class="nav-link link text-warning display-7" href="budget.php">Budget</a></li>
				<li class="nav-item"><a class="nav-link link text-warning display-7" href="goals.php">Goals</a></li>
				<li class="nav-item"><a class="nav-link link text-warning display-7" href="debt.php">Debt</a></li>
				<li class="nav-item"><a class="nav-link link text-warning display-7" href="date.php">Date Span</a></li>
				<li class="nav-item"><a class="nav-link link text-warning display-7" href="report.php">Report</a></li>
			    <?php
			    if(!ss()){
				?><li class="nav-item"><a class="nav-link link text-warning display-7" href="login.php">Login</a></li>
				<li class="nav-item"><a class="nav-link link text-warning display-7" href="register.php">Register</a></li><?php
			    }else{
				?><li class="nav-item"><a class="nav-link link text-warning display-7" href="logout.php">Logout</a></li><?php
			    }
			    ?>
			</ul>
		    </div>
		</div>
	    </nav>
	</section>
<?php
// Gọi ss() chỉ để đảm bảo session đã start và kiểm tra thông báo
ss();
if(isset($_SESSION['SUCCESS'])){
    ?><p id="p_success"><?=$_SESSION['SUCCESS']?></p><?php
    unset($_SESSION['SUCCESS']);
}
if(isset($_SESSION['ERROR'])){
    ?><p id="p_error" ><?=$_SESSION['ERROR']?></p><?php
    unset($_SESSION['ERROR']);
}
?>
<script>
    $('.nav-item.dropdown').on('mouseover', function(){
	$(this).find('.dropdown-menu').not('.dropdown-submenu').show();
    });
    
    $('.nav-item.dropdown').on('mouseout', function(){
	$(this).find('.dropdown-menu').not('.dropdown-submenu').hide();
    });
    
    
    $('.dropdown > .dropdown-item').on('mouseover', function(){
	$('.dropdown-submenu').hide();
	$(this).parent().find('.dropdown-submenu').show();
    });
    
//    $('div.dropdown').on('mouseout', function(){
//	$(this).find('.dropdown-submenu').hide();
//    });
    
    $('.nav-item.dropdown').on('click', function(e){
	$(this).find('.dropdown-menu').show();
	$(this).find('.dropdown-menu').css({
	   'height': 'auto',
	   'display': 'block',
	   'opacity': 1,
	   'visibility': 'visible'
	});
//	e.preventDefault();
	e.stopPropagation();
    });
</script>
