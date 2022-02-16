<template>
    <button class="btn" :type="type" @click="$emit('click', $event)">
        <svg-icon v-if="icon" :icon="icon"></svg-icon> <slot></slot>
    </button>
</template>

<script>
    export default {
        props: {
            type: { type: String, default: 'button' },
            icon: { type: [ Array, String ], default: '' },
        }
    }
</script>
