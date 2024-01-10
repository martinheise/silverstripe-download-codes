<h1>$Title</h1>
<% with $Redemption.Code.Package %>
    <h2>$Title</h2>
    $PreviewImage
    <ul class="downloads">
        <% loop $Files.Sort('Sort')  %>
            <li><a href="$Url">$Title</a></li>
        <% end_loop %>
    </ul>
<% end_with %>
