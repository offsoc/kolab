<template>
    <button class="btn" :type="type" @click="$emit('click', $event)" :disabled="isLoading">
        <span v-if="isLoading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        <svg-icon v-else-if="icon" :icon="icon"></svg-icon> <slot></slot>
    </button>
</template>

<script>
    export default {
        props: {
            type: { type: String, default: 'button' },
            icon: { type: [ Array, String ], default: '' },
            isLoading: { type: Boolean, default: false }
        }
    }
</script>
