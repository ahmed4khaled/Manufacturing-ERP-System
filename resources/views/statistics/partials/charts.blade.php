<script>
const chartData = {{ Illuminate\Support\Js::from($statistics['charts'] ?? []) }};
const colors = {primary:'#4f46e5', teal:'#0f766e', amber:'#d97706', rose:'#e11d48', gray:'#94a3b8'};
const common = {responsive:true, maintainAspectRatio:false, plugins:{legend:{labels:{font:{family:'Tajawal'}}}}, scales:{x:{grid:{display:false}},y:{beginAtZero:true}}};
const canvas = id => document.getElementById(id);
function lineChart(id, data, label, color) { if (!canvas(id) || !data) return; new Chart(canvas(id), {type:'line',data:{labels:data.labels,datasets:[{label,data:data.values,borderColor:color,backgroundColor:color+'22',fill:true,tension:.25}]},options:common}); }
function barChart(id, labels, datasets) { if (!canvas(id)) return; new Chart(canvas(id), {type:'bar',data:{labels,datasets},options:common}); }
function movementChart(id, data) { if (!canvas(id) || !data) return; barChart(id, data.labels, [{label:'وارد',data:data.in,backgroundColor:colors.teal},{label:'صادر',data:data.out,backgroundColor:colors.rose}]); }

lineChart('salesChart', chartData.sales, 'المبيعات', colors.primary);
lineChart('collectionsChart', chartData.collections, 'التحصيلات', colors.teal);
lineChart('returnsChart', chartData.returns, 'المرتجعات', colors.rose);
lineChart('productionChart', chartData.production, 'الإنتاج التام', colors.teal);
lineChart('purchasingChart', chartData.purchasing, 'المشتريات', colors.amber);
if (chartData.planned_vs_produced) barChart('plannedChart', chartData.planned_vs_produced.labels, [{label:'الكمية',data:chartData.planned_vs_produced.values,backgroundColor:[colors.gray,colors.primary]}]);
if (chartData.workers) barChart('workersChart', chartData.workers.labels, [{label:'المنتج',data:chartData.workers.produced,backgroundColor:colors.primary},{label:'العيوب',data:chartData.workers.defects,backgroundColor:colors.rose}]);
if (chartData.materials) barChart('materialsChart', chartData.materials.labels, [{label:'المستخدم',data:chartData.materials.used,backgroundColor:colors.teal},{label:'الهالك',data:chartData.materials.waste,backgroundColor:colors.rose}]);
if (canvas('defectsChart') && chartData.defects) new Chart(canvas('defectsChart'), {type:'doughnut',data:{labels:chartData.defects.labels,datasets:[{data:chartData.defects.values,backgroundColor:['#e11d48','#d97706','#4f46e5','#0f766e','#64748b','#0891b2','#7c3aed']}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
movementChart('productMovementsChart', chartData.product_movements);
movementChart('materialMovementsChart', chartData.material_movements);

const preset = document.getElementById('stats-preset');
const toggleCustomDates = () => document.querySelectorAll('.custom-date').forEach(element => element.classList.toggle('d-none', preset.value !== 'custom'));
preset.addEventListener('change', toggleCustomDates);
toggleCustomDates();
</script>
