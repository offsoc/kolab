<template>
    <div class="flex-center position-ref full-height">
        <div class="code">404</div>
        <div class="message" style="padding: 10px;">Not Found</div>
    </div>
</template>

<style scoped>
    .full-height {
        height: 100vh;
    }

    .flex-center {
        align-items: center;
        display: flex;
        justify-content: center;
    }

    .position-ref {
        position: relative;
        color: #636b6f;
    }

    .code {
        border-right: 2px solid;
        font-size: 26px;
        padding: 0 15px 0 15px;
        text-align: center;
    }

    .message {
        font-size: 18px;
        text-align: center;
    }
</style>
