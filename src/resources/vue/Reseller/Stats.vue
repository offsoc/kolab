<template>
    <div id="stats-container" class="container"></div>
</template>

<script>
    import Stats from '../Admin/Stats'

    export default {
        mixins: [Stats],
        data() {
            return {
//                charts: {},
                chartTypes: ['users', 'users-all', 'discounts']
            }
        }
    }
</script>
