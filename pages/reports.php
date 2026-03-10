<?php
require_once '../includes/auth_check.php';
checkAuth(['admin']);
require_once '../config/db.php';
$db = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports — K.T.S Grocery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-layout">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
<?php include '../includes/header.php'; ?>
<div class="page-content">
    <div class="page-header">
        <div><h1>Reports & Analytics <i class="bi bi-graph-up-arrow" style="color:var(--accent);"></i></h1><p>Business insights and performance reports</p></div>
        <div class="header-actions">
            <a href="../api/reports/index.php?action=export_csv&type=sales" class="btn btn-secondary"><i class="bi bi-download"></i> Export CSV</a>
            <button class="btn btn-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
    </div>

    <!-- Report Nav tabs -->
    <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
        <button class="btn btn-primary report-tab" data-tab="sales" onclick="switchTab(this,'sales')"><i class="bi bi-bar-chart"></i> Sales Report</button>
        <button class="btn btn-secondary report-tab" data-tab="profitloss" onclick="switchTab(this,'profitloss')"><i class="bi bi-currency-dollar"></i> Profit & Loss</button>
        <button class="btn btn-secondary report-tab" data-tab="products" onclick="switchTab(this,'products')"><i class="bi bi-trophy"></i> Top Products</button>
        <button class="btn btn-secondary report-tab" data-tab="stock" onclick="switchTab(this,'stock')"><i class="bi bi-box-seam"></i> Stock Report</button>
        <button class="btn btn-secondary report-tab" data-tab="daily" onclick="switchTab(this,'daily')"><i class="bi bi-calendar-event"></i> Daily Summary</button>
        <button class="btn btn-secondary report-tab" data-tab="tax" onclick="switchTab(this,'tax')"><i class="bi bi-receipt-cutoff"></i> Tax Reports (GST)</button>
    </div>

    <!-- TAX REPORT TAB -->
    <div id="tab-tax" style="display:none;">
        <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
            <div class="form-group mb-0"><label class="form-label" style="display:inline;">From</label> <input type="date" id="taxStart" class="form-control" style="width:auto;display:inline-block;" value="<?= date('Y-m-01') ?>"></div>
            <div class="form-group mb-0"><label class="form-label" style="display:inline;">To</label> <input type="date" id="taxEnd" class="form-control" style="width:auto;display:inline-block;" value="<?= date('Y-m-t') ?>"></div>
            <button class="btn btn-primary btn-sm" onclick="loadTaxReport()">Apply</button>
        </div>
        <div id="taxStatsWrap"></div>
        <div class="card" style="padding:0;"><div id="taxTableWrap"><div style="text-align:center;padding:30px;"><div class="loading-spinner"></div></div></div></div>
    </div>

    <!-- SALES REPORT TAB -->
    <div id="tab-sales">
        <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
            <label style="font-size:13px;color:var(--text-secondary);">Period:</label>
            <button class="btn btn-sm btn-secondary period-btn active" onclick="setPeriod(this,'daily')">30 Days</button>
            <button class="btn btn-sm btn-secondary period-btn" onclick="setPeriod(this,'weekly')">Weekly</button>
            <button class="btn btn-sm btn-secondary period-btn" onclick="setPeriod(this,'monthly')">Monthly</button>
        </div>
        <div class="grid" style="grid-template-columns:2fr 1fr;gap:16px;margin-bottom:20px;">
            <div class="card"><div class="card-header"><div class="card-title"><i class="bi bi-graph-up"></i> Revenue Trend</div></div><div style="height:250px;"><canvas id="salesChart"></canvas></div></div>
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="bi bi-file-text"></i> Summary</div></div>
                <div id="salesSummary"><div class="loading-spinner"></div></div>
            </div>
        </div>
        <div class="card" style="padding:0;"><div id="salesTableWrap"><div style="text-align:center;padding:30px;"><div class="loading-spinner"></div></div></div></div>
    </div>

    <!-- PROFIT & LOSS TAB -->
    <div id="tab-profitloss" style="display:none;">
        <div style="display:flex;gap:10px;margin-bottom:16px;align-items:center;">
            <label style="font-size:13px;color:var(--text-secondary);">Year:</label>
            <input type="number" id="plYear" class="form-control" style="width:100px;" value="<?= date('Y') ?>" onchange="loadPL()">
        </div>
        <div class="card mb-4"><div class="card-header"><div class="card-title"><i class="bi bi-currency-dollar"></i> Profit & Loss</div></div><div style="height:280px;"><canvas id="plChart"></canvas></div></div>
        <div class="card" style="padding:0;"><div id="plTableWrap"><div style="text-align:center;padding:30px;"><div class="loading-spinner"></div></div></div></div>
    </div>

    <!-- TOP PRODUCTS TAB -->
    <div id="tab-products" style="display:none;">
        <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
            <div class="form-group mb-0"><label class="form-label" style="display:inline;">From</label> <input type="date" id="tpStart" class="form-control" style="width:auto;display:inline-block;" value="<?= date('Y-m-01') ?>"></div>
            <div class="form-group mb-0"><label class="form-label" style="display:inline;">To</label> <input type="date" id="tpEnd" class="form-control" style="width:auto;display:inline-block;" value="<?= date('Y-m-t') ?>"></div>
            <button class="btn btn-primary btn-sm" onclick="loadTopProducts()">Apply</button>
        </div>
        <div class="card" style="padding:0;"><div id="topProdWrap"><div style="text-align:center;padding:30px;"><div class="loading-spinner"></div></div></div></div>
    </div>

    <!-- STOCK REPORT TAB -->
    <div id="tab-stock" style="display:none;">
        <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
            <button class="btn btn-sm btn-secondary stock-btn active" onclick="setStockType(this,'current')"><i class="bi bi-box-seam"></i> Current Stock</button>
            <button class="btn btn-sm btn-secondary stock-btn" onclick="setStockType(this,'out')"><i class="bi bi-x-circle"></i> Out of Stock</button>
            <button class="btn btn-sm btn-secondary stock-btn" onclick="setStockType(this,'expiry')"><i class="bi bi-calendar-x"></i> Expiry Report</button>
        </div>
        <div class="card" style="padding:0;"><div id="stockReportWrap"><div style="text-align:center;padding:30px;"><div class="loading-spinner"></div></div></div></div>
    </div>

    <!-- DAILY SUMMARY TAB -->
    <div id="tab-daily" style="display:none;">
        <div style="display:flex;gap:10px;margin-bottom:16px;align-items:center;">
            <label style="font-size:13px;color:var(--text-secondary);">Date:</label>
            <input type="date" id="dailyDate" class="form-control" style="width:auto;" value="<?= date('Y-m-d') ?>" onchange="loadDaily()">
        </div>
        <div id="dailySummaryWrap"><div style="text-align:center;padding:30px;"><div class="loading-spinner"></div></div></div>
    </div>

</div></div></div>

<div id="toast-container"></div>
<script src="../assets/js/app.js"></script>
<script>
let salesChartInstance=null, plChartInstance=null;
let currentPeriod='daily', currentStockType='current';

function switchTab(btn, tab) {
    document.querySelectorAll('.report-tab').forEach(b=>{b.className='btn btn-secondary report-tab';});
    btn.className='btn btn-primary report-tab';
    document.querySelectorAll('[id^="tab-"]').forEach(t=>t.style.display='none');
    document.getElementById('tab-'+tab).style.display='';
    if(tab==='sales') loadSales();
    if(tab==='profitloss') loadPL();
    if(tab==='products') loadTopProducts();
    if(tab==='stock') loadStockReport();
    if(tab==='daily') loadDaily();
    if(tab==='tax') loadTaxReport();
}

async function loadTaxReport() {
    const start = document.getElementById('taxStart').value, end = document.getElementById('taxEnd').value;
    const res = await apiCall(`../api/reports/index.php?action=tax_summary&start=${start}&end=${end}`);
    if(!res || !res.success) return;
    const t = res.totals;
    document.getElementById('taxStatsWrap').innerHTML = `
        <div class="stats-grid" style="margin-bottom:16px;">
            <div class="stat-card blue"><div class="stat-icon blue"><i class="bi bi-calculator"></i></div><div class="stat-info"><div class="stat-label">Taxable Amount</div><div class="stat-value text-blue">${window.APP_CURRENCY} ${parseFloat(t.taxable).toLocaleString()}</div></div></div>
            <div class="stat-card green"><div class="stat-icon green"><i class="bi bi-percent"></i></div><div class="stat-info"><div class="stat-label">Total GST Collected</div><div class="stat-value text-green">${window.APP_CURRENCY} ${parseFloat(t.tax).toLocaleString()}</div></div></div>
            <div class="stat-card purple"><div class="stat-icon purple"><i class="bi bi-wallet2"></i></div><div class="stat-info"><div class="stat-label">Net Total</div><div class="stat-value text-purple">${window.APP_CURRENCY} ${parseFloat(t.total).toLocaleString()}</div></div></div>
        </div>`;
    
    let html = `<div class="table-container" style="border:none;"><table class="table">
        <thead><tr><th>Date</th><th>Taxable Amount</th><th>Tax (GST)</th><th>Net Total</th></tr></thead><tbody>`;
    res.data.forEach(d => {
        html += `<tr>
            <td>${formatDate(d.date)}</td>
            <td>${window.APP_CURRENCY} ${parseFloat(d.taxable_amount).toLocaleString()}</td>
            <td style="font-weight:700;color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(d.tax_amount).toLocaleString()}</td>
            <td>${window.APP_CURRENCY} ${parseFloat(d.net_amount).toLocaleString()}</td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    document.getElementById('taxTableWrap').innerHTML = html;
}

function setPeriod(btn, p){
    document.querySelectorAll('.period-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active'); currentPeriod=p; loadSales();
}
function setStockType(btn, t){
    document.querySelectorAll('.stock-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active'); currentStockType=t; loadStockReport();
}

async function loadSales(){
    const res=await apiCall(`../api/reports/index.php?action=sales_summary&period=${currentPeriod}`);
    if(!res||!res.success) return;
    const data=res.data;
    // Chart
    if(salesChartInstance) salesChartInstance.destroy();
    const ctx=document.getElementById('salesChart').getContext('2d');
    salesChartInstance=new Chart(ctx,{
        type:'bar',
        data:{labels:data.map(d=>d.period_label),datasets:[{label:'Revenue (' + window.APP_CURRENCY + ')',data:data.map(d=>d.revenue),backgroundColor:'rgba(34,197,94,0.25)',borderColor:'#22c55e',borderWidth:2,borderRadius:6}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{color:'#64748b'}},y:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{color:'#64748b',callback:v=>window.APP_CURRENCY + v.toLocaleString()}}}}
    });
    // Summary
    const totalRev=data.reduce((s,d)=>s+parseFloat(d.revenue),0);
    const totalTx=data.reduce((s,d)=>s+parseInt(d.transactions),0);
    const avgTx=totalTx>0?(totalRev/totalTx):0;
    document.getElementById('salesSummary').innerHTML=`
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="padding:14px;background:var(--bg-secondary);border-radius:8px;"><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Total Revenue</div><div style="font-size:22px;font-weight:800;color:var(--accent);">${window.APP_CURRENCY} ${totalRev.toLocaleString('en-LK',{minimumFractionDigits:2})}</div></div>
            <div style="padding:14px;background:var(--bg-secondary);border-radius:8px;"><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Transactions</div><div style="font-size:22px;font-weight:800;color:var(--blue);">${totalTx}</div></div>
            <div style="padding:14px;background:var(--bg-secondary);border-radius:8px;"><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Avg. Sale</div><div style="font-size:22px;font-weight:800;color:var(--amber);">${window.APP_CURRENCY} ${avgTx.toFixed(2)}</div></div>
        </div>`;
    // Table
    let html=`<div class="table-container" style="border:none;"><table class="table"><thead><tr><th>Period</th><th>Revenue</th><th>Discounts</th><th>Transactions</th></tr></thead><tbody>`;
    data.forEach(d=>{
        html+=`<tr><td>${d.period_label}</td><td style="font-weight:700;color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(d.revenue).toLocaleString()}</td><td style="color:var(--red);">-${window.APP_CURRENCY} ${parseFloat(d.discounts).toFixed(2)}</td><td><span class="badge badge-blue">${d.transactions}</span></td></tr>`;
    });
    html+='</tbody></table></div>';
    document.getElementById('salesTableWrap').innerHTML=html;
}

async function loadPL(){
    const year=document.getElementById('plYear').value;
    const res=await apiCall(`../api/reports/index.php?action=profit_loss&year=${year}`);
    if(!res||!res.success) return;
    const data=res.data;
    if(plChartInstance) plChartInstance.destroy();
    const ctx=document.getElementById('plChart').getContext('2d');
    plChartInstance=new Chart(ctx,{
        type:'bar',
        data:{labels:data.map(d=>d.month),datasets:[
            {label:'Revenue',data:data.map(d=>d.revenue),backgroundColor:'rgba(34,197,94,0.3)',borderColor:'#22c55e',borderWidth:2,borderRadius:4},
            {label:'Cost (COGS)',data:data.map(d=>d.cogs),backgroundColor:'rgba(239,68,68,0.3)',borderColor:'#ef4444',borderWidth:2,borderRadius:4},
            {label:'Profit',data:data.map(d=>d.profit),backgroundColor:'rgba(59,130,246,0.3)',borderColor:'#3b82f6',borderWidth:2,borderRadius:4}
        ]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#94a3b8'}}},scales:{x:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{color:'#64748b'}},y:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{color:'#64748b',callback:v=>window.APP_CURRENCY + v.toLocaleString()}}}}
    });
    let html=`<div class="table-container" style="border:none;"><table class="table"><thead><tr><th>Month</th><th>Revenue</th><th>COGS</th><th>Gross Profit</th><th>Margin</th></tr></thead><tbody>`;
    let totRev=0,totCogs=0,totProfit=0;
    data.forEach(d=>{
        const m=parseFloat(d.production_margin||(d.revenue>0?(d.profit/d.revenue*100):0)).toFixed(1);
        totRev+=d.revenue; totCogs+=d.cogs; totProfit+=d.profit;
        const margin=d.revenue>0?((d.profit/d.revenue)*100).toFixed(1):0;
        html+=`<tr><td style="font-weight:500;">${d.month}</td><td style="color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(d.revenue).toLocaleString()}</td><td style="color:var(--red);">${window.APP_CURRENCY} ${parseFloat(d.cogs).toLocaleString()}</td><td style="font-weight:700;color:${d.profit>=0?'var(--accent)':'var(--red)'};">${window.APP_CURRENCY} ${parseFloat(d.profit).toFixed(2)}</td><td><span class="badge ${margin>=50?'badge-green':margin>=20?'badge-amber':'badge-red'}">${margin}%</span></td></tr>`;
    });
    const totMargin=totRev>0?((totProfit/totRev)*100).toFixed(1):0;
    html+=`<tr style="background:var(--bg-secondary);font-weight:700;"><td>TOTAL</td><td style="color:var(--accent);">${window.APP_CURRENCY} ${totRev.toLocaleString()}</td><td style="color:var(--red);">${window.APP_CURRENCY} ${totCogs.toLocaleString()}</td><td style="color:${totProfit>=0?'var(--accent)':'var(--red)'};">${window.APP_CURRENCY} ${totProfit.toFixed(2)}</td><td><span class="badge badge-blue">${totMargin}%</span></td></tr>`;
    html+='</tbody></table></div>';
    document.getElementById('plTableWrap').innerHTML=html;
}

async function loadTopProducts(){
    const start=document.getElementById('tpStart').value, end=document.getElementById('tpEnd').value;
    const res=await apiCall(`../api/reports/index.php?action=top_products&start=${start}&end=${end}`);
    if(!res||!res.success) return;
    if(!res.data.length){ document.getElementById('topProdWrap').innerHTML='<div class="empty-state" style="padding:30px;"><div class="empty-icon"><i class="bi bi-bar-chart" style="font-size:42px;color:var(--text-muted);"></i></div><p>No sales data for this period.</p></div>'; return; }
    const maxQty=Math.max(...res.data.map(p=>p.qty_sold));
    let html=`<div class="table-container" style="border:none;"><table class="table"><thead><tr><th>#</th><th>Product</th><th>Category</th><th>Qty Sold</th><th>Revenue</th><th>Performance</th></tr></thead><tbody>`;
    res.data.forEach((p,i)=>{ html+=`<tr><td style="color:var(--text-muted);">${i+1}</td><td style="font-weight:500;">${escHtml(p.name)}</td><td style="font-size:12px;color:var(--text-secondary);">${escHtml(p.category||'—')}</td><td><span class="badge badge-blue">${p.qty_sold}</span></td><td style="font-weight:700;color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(p.revenue).toLocaleString()}</td><td style="min-width:100px;"><div class="progress"><div class="progress-bar green" style="width:${Math.round((p.qty_sold/maxQty)*100)}%"></div></div></td></tr>`; });
    html+='</tbody></table></div>';
    document.getElementById('topProdWrap').innerHTML=html;
}

async function loadStockReport(){
    const res=await apiCall(`../api/reports/index.php?action=stock_report&type=${currentStockType}`);
    if(!res||!res.success) return;
    if(!res.data.length){ document.getElementById('stockReportWrap').innerHTML='<div class="empty-state" style="padding:30px;"><div class="empty-icon"><i class="bi bi-box-seam" style="font-size:42px;color:var(--text-muted);"></i></div><p>No items found.</p></div>'; return; }
    let html=`<div class="table-container" style="border:none;"><table class="table"><thead><tr><th>Product</th><th>Category</th><th>Barcode</th><th>Qty</th><th>Price</th>`;
    if(currentStockType==='current') html+=`<th>Stock Value</th>`;
    if(currentStockType==='expiry') html+=`<th>Expiry Date</th>`;
    html+=`</tr></thead><tbody>`;
    res.data.forEach(p=>{
        const stockClass=p.quantity==0?'badge-red':p.quantity<=p.min_stock?'badge-amber':'badge-green';
        html+=`<tr><td style="font-weight:500;">${escHtml(p.name)}</td><td style="font-size:12px;color:var(--text-secondary);">${escHtml(p.category_name||'—')}</td><td style="font-family:monospace;font-size:11px;color:var(--blue);">${p.barcode||'—'}</td><td><span class="badge ${stockClass}">${p.quantity}</span></td><td>${window.APP_CURRENCY} ${parseFloat(p.price).toFixed(2)}</td>`;
        if(currentStockType==='current') html+=`<td style="font-weight:600;color:var(--amber);">${window.APP_CURRENCY} ${parseFloat(p.stock_value||0).toFixed(2)}</td>`;
        if(currentStockType==='expiry') html+=`<td style="color:${new Date(p.expiry_date)<new Date()?'var(--red)':'var(--amber)'};">${formatDate(p.expiry_date)}</td>`;
        html+=`</tr>`;
    });
    html+='</tbody></table></div>';
    document.getElementById('stockReportWrap').innerHTML=html;
}

async function loadDaily(){
    const date=document.getElementById('dailyDate').value;
    const res=await apiCall(`../api/reports/index.php?action=daily_summary&date=${date}`);
    if(!res||!res.success) return;
    const t=res.totals;
    let html=`<div class="stats-grid" style="margin-bottom:16px;">
        <div class="stat-card green"><div class="stat-icon green"><i class="bi bi-currency-dollar"></i></div><div class="stat-info"><div class="stat-label">Revenue</div><div class="stat-value text-green">${window.APP_CURRENCY} ${parseFloat(t.revenue).toLocaleString()}</div></div></div>
        <div class="stat-card blue"><div class="stat-icon blue"><i class="bi bi-receipt"></i></div><div class="stat-info"><div class="stat-label">Transactions</div><div class="stat-value text-blue">${t.count}</div></div></div>
        <div class="stat-card red"><div class="stat-icon red"><i class="bi bi-tags"></i></div><div class="stat-info"><div class="stat-label">Discounts</div><div class="stat-value text-red">${window.APP_CURRENCY} ${parseFloat(t.discounts).toFixed(2)}</div></div></div>
    </div>`;
    if(res.data&&res.data.length){
        html+=`<div class="card" style="padding:0;"><div class="table-container" style="border:none;"><table class="table"><thead><tr><th>Invoice</th><th>Time</th><th>Cashier</th><th>Total</th><th>Payment</th></tr></thead><tbody>`;
        res.data.forEach(s=>{ html+=`<tr><td style="font-family:monospace;color:var(--blue);font-size:12px;">${s.invoice_number}</td><td style="font-size:12px;color:var(--text-muted);">${new Date(s.created_at).toLocaleTimeString('en-LK')}</td><td style="color:var(--text-secondary);">${escHtml(s.cashier)}</td><td style="font-weight:700;color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(s.total).toFixed(2)}</td><td><span class="badge ${s.payment_method==='cash'?'badge-green':'badge-blue'}">${s.payment_method}</span></td></tr>`; });
        html+='</tbody></table></div></div>';
    } else { html+='<div class="empty-state"><div class="empty-icon"><i class="bi bi-calendar-event" style="font-size:42px;color:var(--text-muted);"></i></div><p>No sales on this date.</p></div>'; }
    document.getElementById('dailySummaryWrap').innerHTML=html;
}

function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
loadSales();
</script>
</body></html>
