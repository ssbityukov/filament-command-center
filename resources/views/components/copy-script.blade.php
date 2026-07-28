<script>
    document.addEventListener('cc-copy', (event) => {
        navigator.clipboard?.writeText(event.detail.output ?? '')
    })
</script>
