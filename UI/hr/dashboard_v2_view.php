<?php
require_once 'UI/dashboard_layout.php';
?>
<div class="p-4 md:p-6">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Dashboard v2</h1>
    <div class="flex space-x-2">
      <?php $prevMonth = date('n', strtotime('-1 month')); $prevYear = date('Y', strtotime('-1 month')); ?>
      <select id="dash-month" class="bg-gray-700 text-white px-3 py-2 rounded">
        <?php for ($m=1; $m<=12; $m++): ?>
          <option value="<?= $m ?>" <?= $m==$prevMonth?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
        <?php endfor; ?>
      </select>
      <select id="dash-year" class="bg-gray-700 text-white px-3 py-2 rounded">
        <?php $cy=date('Y'); for ($y=$cy-2;$y<=$cy+1;$y++): ?>
          <option value="<?= $y ?>" <?= $y==$prevYear?'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
      <button id="dash-refresh" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Refresh</button>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" id="kpi-cards">
    <div class="bg-gray-800 rounded p-4">
      <div class="text-gray-400 text-xs">Gross</div>
      <div class="text-xl font-semibold" id="kpi-gross">₹0</div>
    </div>
    <div class="bg-gray-800 rounded p-4">
      <div class="text-gray-400 text-xs">Other Deductions</div>
      <div class="text-xl font-semibold text-red-400" id="kpi-statutory">-₹0</div>
    </div>
    <div class="bg-gray-800 rounded p-4">
      <div class="text-gray-400 text-xs">Advance Deduction</div>
      <div class="text-xl font-semibold text-orange-400" id="kpi-advance">-₹0</div>
    </div>
    <div class="bg-gray-800 rounded p-4">
      <div class="text-gray-400 text-xs">Net</div>
      <div class="text-xl font-semibold text-green-400" id="kpi-net">₹0</div>
    </div>
  </div>

  <!-- Two-column: charts + advances -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-2 bg-gray-800 rounded p-4">
      <div class="flex justify-between items-center mb-3">
        <h3 class="font-semibold">Attendance Breakdown</h3>
        <div class="flex items-center space-x-2">
          <input id="att-date" type="date" class="bg-gray-700 text-white px-2 py-1 rounded" />
          <button id="att-refresh" class="bg-gray-700 hover:bg-gray-600 text-white text-xs px-2 py-1 rounded">Load</button>
          <span class="text-xs text-gray-400" id="att-note"></span>
        </div>
      </div>
      <div id="att-chart" class="h-64 flex items-center justify-center text-gray-400">Loading...</div>
    </div>
    <div class="bg-gray-800 rounded p-4">
      <div class="flex justify-between items-center mb-3">
        <h3 class="font-semibold">Top Outstanding Advances</h3>
        <span class="text-xs text-gray-400" id="adv-note"></span>
      </div>
      <div id="adv-list" class="space-y-2 text-sm text-gray-200">Loading...</div>
    </div>
  </div>

  <!-- Team Performance -->
  <div class="bg-gray-800 rounded p-4 mb-6">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Team Performance (Attendance)</h3>
      <div class="flex items-center space-x-2">
        <input id="team-date" type="date" class="bg-gray-700 text-white px-2 py-1 rounded" />
        <button id="team-refresh" class="bg-gray-700 hover:bg-gray-600 text-white text-xs px-2 py-1 rounded">Load</button>
        <span class="text-xs text-gray-400" id="team-note"></span>
      </div>
    </div>
    <div id="team-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm text-gray-200">Loading...</div>
  </div>

  <!-- Map -->
  <div class="bg-gray-800 rounded p-4">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold">Sites Map</h3>
      <span class="text-xs text-gray-400">Locations with coordinates</span>
    </div>
    <div id="sites-map" style="height:420px;" class="rounded overflow-hidden"></div>
  </div>
</div>

<script>
const fmtINR = n => '₹' + (Number(n||0)).toLocaleString('en-IN', {minimumFractionDigits: 0});

async function fetchJSON(url){ const r = await fetch(url); if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); }

async function loadSummary(){
  const m = document.getElementById('dash-month').value;
  const y = document.getElementById('dash-year').value;
  const res = await fetchJSON(`actions/dashboard_v2_controller.php?action=summary&month=${m}&year=${y}`);
  const p = res.data.payroll || {}; const a = res.data.advances || {};
  document.getElementById('kpi-gross').textContent = fmtINR(p.gross || 0);
  document.getElementById('kpi-statutory').textContent = '-' + fmtINR(p.statutory || 0).slice(1);
  document.getElementById('kpi-advance').textContent = '-' + fmtINR(p.advance || 0).slice(1);
  document.getElementById('kpi-net').textContent = fmtINR(p.net || 0);
}

async function loadAttendance(){
  const date = document.getElementById('att-date').value || new Date().toISOString().slice(0,10);
  const res = await fetchJSON(`actions/dashboard_v2_controller.php?action=attendance_daily&date=${date}`);
  const el = document.getElementById('att-chart'); el.innerHTML='';
  const totals = res.data?.totals || [];
  if(totals.length===0){ el.textContent='No data'; return; }
  const total = totals.reduce((s,r)=> s+Number(r.cnt||0),0);
  document.getElementById('att-note').textContent = `Date: ${res.data.date} • Total: ${total}`;
  // Simple bars
  totals.slice(0,8).forEach(r=>{
    const wrap = document.createElement('div'); wrap.className='flex items-center mb-2';
    const lab = document.createElement('div'); lab.className='w-16 text-gray-300 text-xs'; lab.textContent=r.code||'UNK';
    const barWrap = document.createElement('div'); barWrap.className='flex-1 bg-gray-700 h-3 rounded mx-2';
    const bar = document.createElement('div'); bar.className='h-3 rounded bg-blue-500'; bar.style.width = (100*(r.cnt/total)).toFixed(1)+'%';
    const val = document.createElement('div'); val.className='w-12 text-right text-xs text-gray-400'; val.textContent=r.cnt;
    barWrap.appendChild(bar); wrap.appendChild(lab); wrap.appendChild(barWrap); wrap.appendChild(val); el.appendChild(wrap);
  });
}

async function loadTopAdvances(){
  const res = await fetchJSON('actions/dashboard_v2_controller.php?action=top_advances');
  const el = document.getElementById('adv-list'); el.innerHTML='';
  if(!res.data || res.data.length===0){ el.textContent='No active advances'; return; }
  res.data.forEach(r=>{
    const row = document.createElement('div');
    row.className='flex items-center justify-between bg-gray-700 px-3 py-2 rounded';
    const name = `${r.first_name||''} ${r.surname||''}`.trim() || ('#'+r.employee_id);
    row.innerHTML = `<div><div class="font-medium">${name}</div><div class="text-xs text-gray-300">Req: ${r.request_number||r.id}</div></div><div class="text-right"><div class="text-orange-300">${fmtINR(r.remaining_balance)}</div><div class="text-xs text-gray-400">Monthly: ${fmtINR(r.monthly_deduction)}</div></div>`;
    el.appendChild(row);
  });
}

let map, markersLayer;
async function loadMap(){
  const res = await fetchJSON('actions/dashboard_v2_controller.php?action=sites');
  const sites = res.data || [];
  if(!map){
    map = L.map('sites-map').setView([19.0760, 72.8777], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    markersLayer = L.layerGroup().addTo(map);
  }
  markersLayer.clearLayers();
  sites.forEach(s=>{
    if(s.lat && s.lng){
      const m = L.marker([Number(s.lat), Number(s.lng)]);
      m.on('click', async ()=>{
        try{
          const det = await fetchJSON(`actions/dashboard_v2_controller.php?action=site_summary&site_id=${s.id}&days=7`);
          const att = det.data.attendance || [];
          const tickets = det.data.tickets || [];
          const acts = det.data.activities || [];
          let html = `<strong>${s.name||'Site'}</strong><br><div class="text-xs text-gray-800">Last 7 days</div>`;
          if(att.length){ html += '<div class="mt-1"><em>Attendance:</em> ' + att.map(a=>`${a.code}:${a.cnt}`).join(', ') + '</div>'; }
          html += `<div class="mt-1"><em>Tickets:</em> ${tickets.length}</div>`;
          html += `<div class="mt-1"><em>Activities:</em> ${acts.length}</div>`;
          m.bindPopup(html).openPopup();
        }catch(e){ m.bindPopup(`<strong>${s.name||'Site'}</strong><br>ID: ${s.id}`).openPopup(); }
      });
      markersLayer.addLayer(m);
    }
  });
}

async function loadTeamPerformance(){
  const date = document.getElementById('team-date').value || '';
  const params = date ? `date=${date}` : `month=${document.getElementById('dash-month').value}&year=${document.getElementById('dash-year').value}`;
  const res = await fetchJSON(`actions/dashboard_v2_controller.php?action=team_performance&${params}`);
  const el = document.getElementById('team-list'); el.innerHTML='';
  if(!res.data || res.data.length===0){ el.textContent='No team data'; return; }
  res.data.forEach(t=>{
    const card = document.createElement('div'); card.className='bg-gray-700 rounded p-3';
    card.innerHTML = `<div class="font-medium mb-1">${t.team_name||('Team #'+t.team_id)}</div>
      <div class="grid grid-cols-4 gap-2 text-xs">
        <div><div class="text-gray-300">P</div><div class="font-semibold">${t.present||0}</div></div>
        <div><div class="text-gray-300">A</div><div class="font-semibold">${t.absent||0}</div></div>
        <div><div class="text-gray-300">HL</div><div class="font-semibold">${t.holiday||0}</div></div>
        <div><div class="text-gray-300">DBL</div><div class="font-semibold">${t.dbl||0}</div></div>
      </div>`;
    el.appendChild(card);
  });
}

async function refreshAll(){
  // default dates
  const today = new Date().toISOString().slice(0,10);
  if(!document.getElementById('att-date').value) document.getElementById('att-date').value = today;
  if(!document.getElementById('team-date').value) document.getElementById('team-date').value = '';
  try{
    await Promise.all([loadSummary(), loadAttendance(), loadTopAdvances(), loadTeamPerformance(), loadMap()]);
  }catch(e){ console.error(e); }
}

document.getElementById('dash-refresh').addEventListener('click', refreshAll);
document.getElementById('att-refresh').addEventListener('click', loadAttendance);
document.getElementById('team-refresh').addEventListener('click', loadTeamPerformance);
window.addEventListener('load', refreshAll);
</script>
<?php require_once 'UI/dashboard_layout_footer.php'; ?>
