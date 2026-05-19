<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../shared/dashboard_repository.php';
require_once __DIR__ . '/../layouts/role_dashboard.php';
require_auth(['teacher']);
$user=auth_user(); $teacherId=(int)($user['id']??0); $csrf=csrf_token(); $error=flash_get('error'); $success=flash_get('success'); $testId=(int)($_GET['test_id']??0); $qTypeFilter=trim((string)($_GET['qtype']??''));
$db=Database::connection(); ensure_teacher_tables($db);
$test=null; $questions=[];
if($testId>0){
  $st=$db->prepare('SELECT t.id,t.title,t.duration_minutes,s.name AS subject_name FROM tests t LEFT JOIN subjects s ON s.id=t.subject_id WHERE t.id=? AND t.teacher_id=? LIMIT 1');
  $st->bind_param('ii',$testId,$teacherId); $st->execute(); $test=$st->get_result()->fetch_assoc(); $st->close();
  if($test){
    if(in_array($qTypeFilter,['open','closed'],true)){
      $sq=$db->prepare('SELECT id,question_text,question_type,option_a,option_b,option_c,option_d,correct_option,created_at FROM test_questions WHERE test_id=? AND teacher_id=? AND question_type=? ORDER BY id DESC');
      $sq->bind_param('iis',$testId,$teacherId,$qTypeFilter);
    } else {
      $sq=$db->prepare('SELECT id,question_text,question_type,option_a,option_b,option_c,option_d,correct_option,created_at FROM test_questions WHERE test_id=? AND teacher_id=? ORDER BY id DESC');
      $sq->bind_param('ii',$testId,$teacherId);
    }
    $sq->execute(); $r=$sq->get_result(); while($x=$r->fetch_assoc()){$questions[]=$x;} $sq->close();
  }
}
ob_start(); ?>
<?php if ($error || $success): ?><div class="rounded-xl px-4 py-3 text-sm font-semibold text-white <?= $error ? 'bg-rose-500' : 'bg-emerald-500' ?>"><?= h($error ?: $success) ?></div><?php endif; ?>
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
<style>.ql-container{min-height:120px}.q-html{display:none}.ql-editor img{max-width:100%;height:auto}</style>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
<div class="flex items-center justify-between"><h2 class="font-heading text-lg font-semibold">Savollar: <?= h($test['title']??'-') ?></h2><a href="/test/teacher/tests.php" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Orqaga</a></div>
<p class="text-sm text-slate-500">Fan: <?= h($test['subject_name']??'-') ?> | Davomiyligi: <?= (int)($test['duration_minutes']??0) ?> min</p>
<div class="flex items-center gap-2"><button id="openAddQ" class="rounded-xl bg-green-700 px-4 py-2 text-white">+ Savol qo'shish</button><a href="/test/teacher/export-test-questions.php?test_id=<?= (int)$testId ?>" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Export (CSV)</a><form method="GET" class="flex items-center gap-2"><input type="hidden" name="test_id" value="<?= (int)$testId ?>"><select name="qtype" class="rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Barcha turlar</option><option value="open" <?= $qTypeFilter==='open'?'selected':'' ?>>Ochiq</option><option value="closed" <?= $qTypeFilter==='closed'?'selected':'' ?>>Yopiq</option></select><button class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Filter</button></form></div>
<div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">Savol</th><th class="px-3 py-2 text-left">Turi</th><th class="px-3 py-2 text-left">To'g'ri javob</th></tr></thead><tbody><?php foreach($questions as $q): ?><tr class="border-t"><td class="formula-content px-3 py-2"><?= strip_tags((string)$q['question_text'],'<b><strong><i><em><u><s><sub><sup><ol><ul><li><p><br><span><div><img>') ?></td><td class="px-3 py-2"><?= h($q['question_type']??'closed') ?></td><td class="px-3 py-2"><?= h($q['correct_option']??'-') ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>
<div id="formulaModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/40 p-4">
  <div class="w-full max-w-3xl max-h-[85vh] overflow-y-auto rounded-2xl bg-white p-4">
    <div class="mb-2 flex items-center justify-between">
      <h3 class="font-heading text-lg font-semibold">Formula panel</h3>
      <button type="button" data-close="formulaModal">X</button>
    </div>
    <div id="formulaBody"></div>
  </div>
</div>
<div id="qModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4"><div class="w-full max-w-5xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-5"><div class="mb-3 flex items-center justify-between"><h3 class="font-heading text-xl font-semibold">Savol qo'shish</h3><button type="button" data-close="qModal">X</button></div><form method="POST" action="/test/teacher/insert/test-questions.php" class="space-y-3" id="qForm"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="test_id" value="<?= (int)$testId ?>"><div id="qWrap" class="max-h-[60vh] overflow-y-auto space-y-4 pr-1"></div><div class="flex gap-2"><button type="button" id="addQ" class="rounded-xl border border-emerald-500 px-4 py-2 text-emerald-700">+ Yana savol</button><button class="rounded-xl bg-green-700 px-4 py-2 text-white">Saqlash</button></div></form></div></div>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
const byId=(x)=>document.getElementById(x); const open=(x)=>{byId(x)?.classList.remove('hidden');byId(x)?.classList.add('flex');}; const close=(x)=>{byId(x)?.classList.add('hidden');byId(x)?.classList.remove('flex');};
let i=0; const wrap=byId('qWrap'); const editors=[];
const toolbar=[['bold','italic','underline','strike'],[{'size':['small',false,'large','huge']}],[{'align':[]}],[{'list':'ordered'},{'list':'bullet'}],['subscript','superscript'],[{'color':[]}],['image']];
function block(n){return `<div class='rounded-xl border p-3 space-y-3'><div class='editor question-editor'></div><input type='hidden' name='items[${n}][question_text]' class='q-html' required><select name='items[${n}][question_type]' class='qtype w-full rounded-xl border px-3 py-2'><option value='closed'>Yopiq</option><option value='open'>Ochiq</option></select><div class='variants hidden space-y-3'><label class='block text-xs font-semibold text-slate-600'>A varianti</label><div class='editor option-a'></div><input type='hidden' name='items[${n}][option_a]' class='q-html'><label class='block text-xs font-semibold text-slate-600'>B varianti</label><div class='editor option-b'></div><input type='hidden' name='items[${n}][option_b]' class='q-html'><label class='block text-xs font-semibold text-slate-600'>C varianti</label><div class='editor option-c'></div><input type='hidden' name='items[${n}][option_c]' class='q-html'><label class='block text-xs font-semibold text-slate-600'>D varianti</label><div class='editor option-d'></div><input type='hidden' name='items[${n}][option_d]' class='q-html'><select name='items[${n}][correct_option]' class='w-full rounded-xl border px-3 py-2'><option value=''>To\'g\'ri javob</option><option>A</option><option>B</option><option>C</option><option>D</option></select></div></div>`;}
function initQuill(el){return new Quill(el,{theme:'snow',modules:{toolbar}});}
const formulaTemplates={
  'Kasrlar':[
    {label:'Oddiy kasr a/b', latex:'\\frac{a}{b}'},
    {label:'Murakkab kasr', latex:'\\frac{a+b}{c+d}'},
    {label:'Aralash kasr', latex:'a \\frac{b}{c}'}
  ],
  'Darajalar':[
    {label:'Kvadrat x²', latex:'x^2'},
    {label:'Daraja xⁿ', latex:'x^n'}
  ],
  'Indekslar':[
    {label:'Pastki indeks x₁', latex:'x_1'},
    {label:'Pastki indeks xₙ', latex:'x_n'}
  ],
  'Ildizlar':[
    {label:'Kvadrat ildiz √x', latex:'\\sqrt{x}'},
    {label:'Kub ildiz ∛x', latex:'\\sqrt[3]{x}'}
  ],
  'Integral':[
    {label:'Integral', latex:'\\int_a^b f(x)dx'},
    {label:'Ikki integral', latex:'\\iint_D f(x,y)dA'}
  ],
  'Sigma':[
    {label:'Yig‘indi Σ', latex:'\\sum_{i=1}^{n} i'},
    {label:'Ko‘paytma Π', latex:'\\prod_{i=1}^{n} i'}
  ],
  'Limit':[
    {label:'Limit', latex:'\\lim_{x \\to 0} \\frac{\\sin x}{x}'}
  ],
  'Matritsa':[
    {label:'2x2 matritsa', latex:'\\begin{bmatrix}a & b\\\\ c & d\\end{bmatrix}'}
  ],
  'Geometriya':[
    {label:'Burchak ∠', latex:'\\angle ABC'},
    {label:'Gradus °', latex:'90^\\circ'},
    {label:'Perpendikulyar ⟂', latex:'AB \\perp CD'}
  ],
  'Greek symbols':[
    {label:'π', latex:'\\pi'},
    {label:'α', latex:'\\alpha'},
    {label:'β', latex:'\\beta'},
    {label:'θ', latex:'\\theta'},
    {label:'λ', latex:'\\lambda'}
  ]
};
let activeFormulaQuill=null;
function openFormulaModal(q){
  activeFormulaQuill=q;
  const body=byId('formulaBody');
  body.innerHTML='';
  Object.entries(formulaTemplates).forEach(([title, arr])=>{
    const sec=document.createElement('div');
    sec.className='mb-3';
    const h=document.createElement('div'); h.className='mb-1 text-sm font-semibold'; h.textContent=title; sec.appendChild(h);
    const w=document.createElement('div'); w.className='flex flex-wrap gap-2';
    arr.forEach((item)=>{
      const b=document.createElement('button');
      b.type='button'; b.className='rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50';
      b.textContent=item.label; b.onclick=()=>insertFormula(item.latex); w.appendChild(b);
    });
    sec.appendChild(w); body.appendChild(sec);
  });
  open('formulaModal');
}
function insertFormula(latex){
  if(!activeFormulaQuill) return;
  const r=activeFormulaQuill.getSelection(true)||{index:activeFormulaQuill.getLength(),length:0};
  const token=`\\(${latex}\\)`;
  activeFormulaQuill.insertText(r.index, token, 'user');
  activeFormulaQuill.setSelection(r.index+token.length,0,'silent');
  close('formulaModal');
}
function addMathHelpers(editorRoot, q){
  const box=document.createElement('div');
  box.className='mb-2';
  box.innerHTML="<button type='button' class='rounded border border-slate-300 px-2 py-1 text-xs font-semibold'>fx Formula</button>";
  editorRoot.parentNode.insertBefore(box, editorRoot);
  box.querySelector('button').addEventListener('click',()=>openFormulaModal(q));
}
function bindBlock(el){
  const qRoot=el.querySelector('.question-editor'); const q=initQuill(qRoot); addMathHelpers(qRoot,q); const hiddenQ=el.querySelector('input[name*="[question_text]"]'); editors.push([q,hiddenQ]);
  ['a','b','c','d'].forEach((k)=>{const oRoot=el.querySelector('.option-'+k); const qe=initQuill(oRoot); addMathHelpers(oRoot,qe); const hi=el.querySelector(`input[name*="[option_${k}]"]`); editors.push([qe,hi]);});
  const s=el.querySelector('.qtype'); const v=el.querySelector('.variants');
  const toggle=()=>{const openType=s.value==='open'; v.classList.toggle('hidden',!openType); v.querySelectorAll('select').forEach(x=>x.required=openType);};
  s.addEventListener('change',toggle); toggle();
}
byId('openAddQ')?.addEventListener('click',()=>{open('qModal'); if(wrap.children.length===0){wrap.insertAdjacentHTML('beforeend',block(i)); bindBlock(wrap.lastElementChild); i++;}});
byId('addQ')?.addEventListener('click',()=>{wrap.insertAdjacentHTML('beforeend',block(i)); bindBlock(wrap.lastElementChild); i++;});
document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',()=>close(b.getAttribute('data-close'))));
byId('qForm')?.addEventListener('submit',(e)=>{editors.forEach(([q,h])=>h.value=q.root.innerHTML.trim()); const empty=[...document.querySelectorAll('input[name*="[question_text]"]')].every(x=>(x.value||'').replace(/<(.|\n)*?>/g,'').trim()===''); if(empty){e.preventDefault(); alert('Kamida bitta savol kiriting.');}});
if (window.MathJax && window.MathJax.typesetPromise) {
  window.MathJax.typesetPromise(document.querySelectorAll('.formula-content'));
}
</script>
<?php $content=ob_get_clean(); render_dashboard_layout(['title'=>'Savollar','page_title'=>'Test savollari','subtitle'=>'Savol qo\'shish','role_name'=>'Teacher','menu'=>[['key'=>'dashboard','label'=>'Dashboard','icon'=>'dashboard','href'=>'/test/teacher/dashboard.php'],['key'=>'subjects','label'=>'Fanlar','icon'=>'teachers','href'=>'/test/teacher/subjects.php'],['key'=>'tests','label'=>'Testlar','icon'=>'tests','href'=>'/test/teacher/tests.php']],'active'=>'tests','user'=>$user,'content'=>$content]);
