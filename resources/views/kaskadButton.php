<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
</head>

<body>
    <div class="container py-3">
        <div class="skeleton skeleton-btn" id="button-skeleton"></div>
        <span id="button" onclick="prepareData()" class="w-100 btn btn-primary me-2 d-none">Передать в Айболит</span>
    </div>

    <script>
    window.b24InstanceId = null
    window.b24InstanceType = null

  
    function prepareData() {
        document.querySelector('#button').classList.add('d-none');
        document.querySelector('#button-skeleton').classList.remove('d-none')

        let options = {
            bitrix_id: BX24.placement.info().options.ENTITY_VALUE_ID
        }
        axios.post('url', {options: options})
        document.querySelector('#button-skeleton').classList.add('d-none');
        document.querySelector('#button').classList.remove('d-none')
    }

    BX24.init(() => {
    	prepareData();
    })

    </script>
    <style>
    body {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        overflow: auto;
        background: #f9fafb;
    }

    .container{
        box-shadow:inset 0px 0px 0px 1px #edeef0;
        border-radius: 6px;
        background-color: #fdfdfd;
    }

    .skeleton{
        animation: skeleton-loading 1s linear infinite alternate;
    }
    .skeleton.loaded{
        animation: none;
    }

    @keyframes skeleton-loading {
      0% {
        background-color: hsl(200, 20%, 80%);
      }
      100% {
        background-color: hsl(200, 20%, 95%);
      }
    }

    .skeleton-line{
        height: 12px;
        border-radius: 5px;
        margin-bottom: 4px;
    }
    .skeleton-btn{
        height: 38px;
        /*border: 1px solid hsl(200, 20%, 80%);*/
    }
    .heading .skeleton-line{
       height: 16px;
    }


    .hide-loading{
        display: none!important;
    }
    .show-loading{
        display: block;
    }
    .show-loading.loaded{
        display: none!important;
    }

    </style>
</body>

</html>
