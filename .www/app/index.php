<?php
$webroot = __DIR__; // starting point

// Recursively scan webroot, skip config files/folders
function scanDirRecursive($dir, $base=''){
    $result = [];
    $items = scandir($dir);
    foreach($items as $item){
        if(in_array($item,['.','..'])) continue;
        $path = $dir.'/'.$item;
        $rel = ltrim($base.'/'.$item,'/');
        if(strpos($rel,'config')!==false) continue;
        if(is_dir($path)){
            $result[] = ['type'=>'dir','name'=>$item,'path'=>$rel,'children'=>scanDirRecursive($path,$rel)];
        } else {
            $result[] = ['type'=>'file','name'=>$item,'path'=>$rel];
        }
    }
    return $result;
}

// AJAX actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if($action==='list'){
    header('Content-Type: application/json');
    echo json_encode(scanDirRecursive($webroot));
    exit;
}
if($action==='load'){
    $file = realpath($webroot.'/'.$_POST['file']);
    if(!$file || strpos($file,$webroot)!==0 || !is_file($file)) { http_response_code(400); echo 'Invalid file'; exit; }
    echo file_get_contents($file);
    exit;
}
if($action==='save'){
    $file = realpath($webroot.'/'.$_POST['file']);
    if(!$file || strpos($file,$webroot)!==0 || !is_file($file)) { http_response_code(400); echo 'Invalid file'; exit; }
    file_put_contents($file,$_POST['content'] ?? '');
    echo 'ok';
    exit;
}
if($action==='create'){
    $name = trim($_POST['name'] ?? '');
    if(!$name){ http_response_code(400); echo 'Missing name'; exit; }
    $target = $webroot.'/'.$name;
    if(file_exists($target)){ http_response_code(400); echo 'Already exists'; exit; }
    if(strpos($name,'.')===false) mkdir($target,0755,true);
    else file_put_contents($target,'');
    echo 'ok';
    exit;
}
if($action==='move'){
    $file = realpath($webroot.'/'.$_POST['file']);
    $target = $webroot.'/'.trim($_POST['target']);
    if(!$file || strpos($file,$webroot)!==0) { http_response_code(400); echo 'Invalid file'; exit; }
    if(file_exists($target)){ http_response_code(400); echo 'Target exists'; exit; }
    rename($file,$target) or die('Move failed');
    echo 'ok';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Web Builder</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f5f5f5; color: #222; }
.file-tree { max-height: 400px; overflow-y: auto; background: #fff; border:1px solid #ccc; padding:10px; border-radius:8px; }
.file-item { padding:4px 8px; cursor:pointer; font-family: monospace; }
.file-item:hover { background:#eee; }
#editor { height:300px; font-family: monospace; background:#fff; border:1px solid #ccc; border-radius:4px; padding:8px; }
#preview { height:300px; border:1px solid #ccc; border-radius:4px; background:#fff; padding:8px; overflow:auto; }
</style>
</head>
<body>
<div class="container my-3">
    <h3>Web Builder Editor</h3>
    <div class="row">
        <div class="col-md-4">
            <h5>File Tree</h5>
            <div class="file-tree" id="fileList"></div>
            <input type="text" id="newFileName" class="form-control my-1" placeholder="New file/folder name">
            <button class="btn btn-primary btn-sm mb-1" onclick="createFile()">Create File/Folder</button>
            <button class="btn btn-secondary btn-sm mb-1" onclick="refreshFiles()">Refresh</button>
        </div>
        <div class="col-md-8">
            <h5>Editor & Preview</h5>
            <select id="selectFile" class="form-select mb-1" onchange="loadFile()"></select>
            <textarea id="editor"></textarea>
            <div class="my-2">
                <button class="btn btn-success btn-sm" onclick="saveFile()">Save</button>
                <button class="btn btn-warning btn-sm" onclick="moveFile()">Move</button>
                <input type="text" id="moveTarget" class="form-control mt-1" placeholder="Move to path relative to webroot">
            </div>
            <h5 class="mt-2">Preview</h5>
            <iframe id="preview" style="width:100%;"></iframe>
        </div>
    </div>
</div>

<script>
function ajax(action,data={}) {
    const f = new FormData();
    f.append('action',action);
    for(let k in data) f.append(k,data[k]);
    return fetch('',{method:'POST',body:f}).then(r=>r.text());
}

async function refreshFiles(){
    const r = await ajax('list');
    const list = JSON.parse(r);
    const fileList = document.getElementById('fileList');
    const selectFile = document.getElementById('selectFile');
    fileList.innerHTML=''; selectFile.innerHTML='';
    function render(items,parent){
        items.forEach(i=>{
            const div=document.createElement('div');
            div.className='file-item';
            div.textContent=i.path;
            div.onclick=()=>{ selectFile.value=i.path; loadFile(); };
            parent.appendChild(div);
            const opt=document.createElement('option');
            opt.value=i.path; opt.text=i.path;
            selectFile.appendChild(opt);
            if(i.type==='dir' && i.children) render(i.children,parent);
        });
    }
    render(list,fileList);
}

async function createFile(){
    const n=document.getElementById('newFileName').value.trim();
    if(!n) return alert('Enter name');
    const r=await ajax('create',{name:n});
    if(r==='ok'){ alert('Created'); refreshFiles(); } else alert(r);
}

async function loadFile(){
    const f=document.getElementById('selectFile').value;
    if(!f) return;
    const content=await ajax('load',{file:f});
    document.getElementById('editor').value=content;
    document.getElementById('preview').srcdoc=content;
}

async function saveFile(){
    const f=document.getElementById('selectFile').value;
    const content=document.getElementById('editor').value;
    if(!f) return alert('Select a file');
    const r=await ajax('save',{file:f, content:content});
    if(r==='ok') alert('Saved'); else alert(r);
    document.getElementById('preview').srcdoc=content;
}

async function moveFile(){
    const f=document.getElementById('selectFile').value;
    const t=document.getElementById('moveTarget').value.trim();
    if(!f || !t) return alert('Select file and target');
    const r=await ajax('move',{file:f,target:t});
    if(r==='ok'){ alert('Moved'); refreshFiles(); } else alert(r);
}

document.getElementById('editor').addEventListener('input',e=>{
    document.getElementById('preview').srcdoc=e.target.value;
});

refreshFiles();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>