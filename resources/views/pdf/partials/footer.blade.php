@php($footerPath=public_path('imagenes/institucional/plantilla-pie.png'))
<footer class="institutional-footer">
 @if(is_file($footerPath))<img src="data:image/png;base64,{{base64_encode(file_get_contents($footerPath))}}" alt="Información y certificaciones institucionales">@endif
</footer>
