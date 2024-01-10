<h1>$Title</h1>

<% with $Redemption.Code.Package %>
    <h2>$Title</h2>
    $PreviewImage.Fit(200, 200)
    <h3>Downloads</h3>
    <ul>
        <% loop $Files.Sort('Sort') %>
            <li><a href="$Url">$Title ($Size)</a></li>
        <% end_loop %>
    </ul>
    <% if $ZippedFiles %>
        <p><a href="$ZippedFiles.URL">Download all files as ZIP</a></p>
    <% end_if %>
<% end_with %>
