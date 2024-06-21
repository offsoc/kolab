<template>
    <div id="stats-container" class="container"></div>
</template>

<script>
    import Stats from '../Admin/Stats'

    export default {
        mixins: [Stats],
        data() {
            return {
                chartTypes: ['users', 'users-all', 'payers', 'discounts', 'vouchers']
            }
        }
    }
</script>
