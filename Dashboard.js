// Cash Flow Chart Data
const ctx = document.getElementById('cashFlowChart').getContext('2d');

const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                'July', 'August', 'September', 'October', 'November', 'December'];

// incomeData and expensesData are dynamically injected from Dashboard.php

const cashFlowChart = new Chart(ctx, {
  type: 'bar',
  data: {
    labels: months,
    datasets: [
      {
        label: 'Income',
        data: incomeData,
        backgroundColor: '#00A86B',
        borderColor: '#008C5C',
        borderWidth: 1,
        borderRadius: 5,
      },
      {
        label: 'Expenses',
        data: expensesData,
        backgroundColor: '#FF6B6B',
        borderColor: '#E85555',
        borderWidth: 1,
        borderRadius: 5,
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
        position: 'top',
        labels: {
          boxWidth: 15,
          padding: 15,
          font: {
            size: 12,
            weight: 600
          },
          color: '#333'
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          callback: function(value) {
            return 'FCFA ' + value.toLocaleString();
          },
          font: {
            size: 11
          }
        },
        grid: {
          color: 'rgba(0, 0, 0, 0.05)'
        }
      },
      x: {
        grid: {
          display: false
        },
        ticks: {
          font: {
            size: 11
          }
        }
      }
    }
  }
});