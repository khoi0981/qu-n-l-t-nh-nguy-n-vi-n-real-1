<?php
$con = mysqli_connect("localhost", "root", "", "expense_tracker");
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}
include './Includes/Functions/functions.php';
include './Includes/Functions/auth.php';
$user_id = $_SESSION['user']['id'];

// Sửa lại truy vấn lấy báo cáo (đúng tên cột)
$sql = "SELECT BUDGET.amount AS BUDGET, EXPENSES.amount AS EXPENSE, (BUDGET.amount - EXPENSES.amount) AS DEBT, EXPENSES.description AS DESCRIPTION
        FROM BUDGET INNER JOIN EXPENSES ON BUDGET.user_id = EXPENSES.user_id
        WHERE BUDGET.user_id = $user_id AND EXPENSES.user_id = $user_id";
$result = mysqli_query($con, $sql);
if (!$result) {
    die("Query failed: " . mysqli_error($con));
}
$debt = array();
while ($row = mysqli_fetch_assoc($result)) {
    $debt[] = $row;
}
include './top_scripts.php';
error_reporting(E_ALL); ini_set('display_errors', 1);
// Lấy username đúng user hiện tại
$sql3 = "SELECT username AS uname FROM USERS WHERE id = $user_id";
$result3 = mysqli_query($con, $sql3);
$output4 = '';
if ($row3 = mysqli_fetch_assoc($result3)) {
    $output4 = $row3['uname'];
}
// Tổng budget (đúng tên cột)
$sql1 = "SELECT SUM(amount) AS sum1 FROM BUDGET WHERE user_id = $user_id";
$result1 = mysqli_query($con, $sql1);
$output1 = 0;
if ($row1 = mysqli_fetch_assoc($result1)) {
    $output1 = $row1['sum1'];
}
// Tổng expense (đúng tên cột)
$sql2 = "SELECT SUM(amount) as sum2 FROM EXPENSES WHERE user_id = $user_id";
$result2 = mysqli_query($con, $sql2);
$output2 = 0;
if ($row2 = mysqli_fetch_assoc($result2)) {
    $output2 = $row2['sum2'];
}
?>
<html>
<style>
.flex{display:flex;justify-content:space-around;align-items:center;}
.pie{min-width: 600px;display:flex;flex-direction:column;align-items:center;}
.pie h1{margin-top:30px;}
.features7{margin-top:40px;padding-bottom:0;margin-right:2em;background-color:#fff;}
.abc{font-family: monospace;float:left;margin-left:15px;}
.koibhi{text-align:center;display:block;}
.container{margin-top:-60px;padding-bottom:0;}
.submit{width:5rem;height:2.5rem;margin-left:50vw;border:none;font-family:monospace;background-color:orange;border-radius:12%}
</style>
<head>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(drawChart);
function drawChart() {
    var data = google.visualization.arrayToDataTable([
        ['description', 'amount'],
        <?php
        $sql = "SELECT * FROM expenses WHERE user_id = $user_id";
        $fire = mysqli_query($con, $sql);
        while ($result = mysqli_fetch_assoc($fire)) {
            echo "['" . $result['description'] . "'," . $result['amount'] . "],";
        }
        ?>
    ]);
    var options = {
        title: 'Total Expenses'
    };
    var chart = new google.visualization.PieChart(document.getElementById('piechart'));
    chart.draw(data, options);
}
</script>
</head>
<body>
<section class="koibhi">
    <h1><b>EXPENSE TRACKER</b></h1>
    <span class="abc"><?php echo "Username =<b>$output4</b><br>";?></span><br>
    <span class="abc"><?php echo "Your Total Budget is=<b>$output1</b><br>";?></span><br>
    <span class="abc"><?php echo "Your Total Expense is=<b>$output2</b><br>";?></span><br>
    <span class="abc"><?php $output3 = $output1 - $output2; echo "Your Debt is=<b>$output3</b><br>";?></span>
</section>
<div class="flex">
<div class="pie">
<h1>Piechart</h1>
<div id="piechart" style="width: 600px; height: 400px;"></div>
</div>
<section class="features7 cid-sENIyiRsb8" id="features08-3" style="min-height: 500px;">
    <div class="container">
    <div class="mbr-section-head pb-5">
        <h4 class="mbr-section-title mbr-fonts-style align-center mb-0 display-2">
        <strong>Report</strong></h4>
    </div>
    <div class="row ">
        <?php
        if (!empty($debt)) {
        ?><table class="table table-bordered table-striped table-condensed">
            <thead>
            <tr>
                <th>DESCRIPTION</th>
                <th>BUDGET</th>
                <th>EXPENSE</th>
                <th>DEBT</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($debt as $deb) {
            ?><tr>
                <td><?= $deb['DESCRIPTION'] ?></td>
                <td><?= $deb['BUDGET'] ?></td>
                <td><?= $deb['EXPENSE'] ?></td>
                <td><?= $deb['DEBT'] ?></td>
            </tr><?php
            }
            ?>
            </tbody>
        </table><?php
        } else {
        ?><h4>No DEBT yet</h4><?php
        }
        ?>
</section>
</div>
<button class="submit">PRINT</button>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
const btn = document.querySelector(".submit");
var ele = document.querySelector(".koibhi");
var el = document.querySelector(".submit");
btn.addEventListener("click", function(){
    var element = document.querySelector("body");
    var opt = {
        jsPDF: {unit:'in',format:'A4',orientation:'landscape'}
    }
    ele.style.display = "block";
    el.style.display = "none";
    html2pdf().set(opt).from(element).save('filename.pdf');
});
</script>
</body>
</html>
