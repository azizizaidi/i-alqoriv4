<div>
@if(auth()->user()->roles->contains(1) || auth()->user()->roles->contains(2))
<div x-data="{ chart: null }" x-init="setTimeout(() => { document.getElementById('yearSelect').value = '2023'; updateChart(); }, 500)">
  <h1>Jumlah Yuran Bulanan & Elaun Guru</h1>
  <hr>

  <div class="m-auto">
    <div class="">
        <label for="year">Pilih Tahun:</label>
        <select id="yearSelect" onchange="updateChart()">
            <option value="">Pilih Tahun</option>
            <option value="2022">2022</option>
            <option value="2023">2023</option>
            <option value="2024">2024</option>
            <option value="2025">2025</option>
        </select>
    </div>

    <div class="col-lg-10">
      <div class="card text-grey bg-white rounded-3 shadow p-1">
        <div class="body">
          <canvas id="myChart" class="chartjs" data-height="400" height="500" style="display: block; box-sizing: border-box; height: 400px; width: 592.8px;" width="741"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>
@endif

<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.3/dist/Chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@0.7.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>

// Store all report classes data in a JavaScript variable
var reportClassesData = <?php echo json_encode($reportclasses); ?>;

// Function to get fee data for a specific month and year
function getFeeForMonth(month, year) {
    // Handle special case for January 2022 which uses null as month
    if (month === '01' && year === '2022') {
        return reportClassesData
            .filter(item => item.month === null && item.deleted_at === null)
            .reduce((sum, item) => sum + (parseFloat(item.fee_student) || 0), 0);
    }

    // Format month-year string (e.g., "01-2023")
    var monthYearFormat = month + '-' + year;

    // Filter and sum fees for the specified month-year
    return reportClassesData
        .filter(item => item.month === monthYearFormat && item.deleted_at === null)
        .reduce((sum, item) => sum + (parseFloat(item.fee_student) || 0), 0);
}

// Function to get allowance data for a specific month and year
function getAllowanceForMonth(month, year) {
    // Handle special case for January 2022 which uses null as month
    if (month === '01' && year === '2022') {
        return reportClassesData
            .filter(item => item.month === null && item.deleted_at === null)
            .reduce((sum, item) => sum + (parseFloat(item.allowance) || 0), 0);
    }

    // Format month-year string (e.g., "01-2023")
    var monthYearFormat = month + '-' + year;

    // Filter and sum allowances for the specified month-year
    return reportClassesData
        .filter(item => item.month === monthYearFormat && item.deleted_at === null)
        .reduce((sum, item) => sum + (parseFloat(item.allowance) || 0), 0);
}

// Define the chart data and options

var chartData = {
    labels: ['Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November','Disember'],
    datasets: [{
      backgroundColor: 'rgba(0,0,255,1.0)',
      borderColor: 'rgba(0,0,255,0.1)',
      data: [],
      label: 'Jumlah Yuran(RM)',
    }, {
      backgroundColor: 'rgba(255, 99, 71, 1)',
      borderColor: 'rgba(255, 108, 49, 0.3)',
      data: [],
      label: 'Jumlah Elaun(RM)',
    }]
  };

var chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  legend: {
    display: true,
  },
  scales: {
    yAxes: [{ ticks: { min: 0, max: 50000 } }],
    xAxes: [{
      ticks: {
        maxRotation: 45,
        minRotation: 45
      },
      categoryPercentage: 0.5, // Adjust this value to increase space between groups
      barPercentage: 0.5       // Adjust this value to control the bar width
    }]
  },
  plugins: {
    datalabels: {
      anchor: 'end',
      align: 'end',
      color: 'black',
      font: {
        weight: 'bold'
      },
      formatter: function(value, context) {
        return 'RM' + value;
      },
      display: function(context) {
        return window.innerWidth >= 768; // Hide labels on screens smaller than 768px
      }
    }
  }
};

// Create an empty chart instance
var chart = new Chart(document.getElementById('myChart'), {
  type: 'bar', // Change this line to 'bar'
  data: chartData,
  options: chartOptions,
  plugins: [ChartDataLabels] // Add this line to enable the datalabels plugin
});

// Function to update the chart based on the selected year
function updateChart() {
  var selectedYear = document.getElementById('yearSelect').value;
  var feeData = [];
  var allowanceData = [];

  if (selectedYear) {
    // Generate data for all months in the selected year
    var months = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];

    // For each month, get the fee and allowance data
    feeData = months.map(function(month) {
      return getFeeForMonth(month, selectedYear);
    });

    allowanceData = months.map(function(month) {
      return getAllowanceForMonth(month, selectedYear);
    });
  } else {
    // If no year is selected, show empty data
    feeData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    allowanceData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
  }

  // Update the chart data
  chart.data.datasets[0].data = feeData;
  chart.data.datasets[1].data = allowanceData;
  chart.update();
}

</script>

</div>
