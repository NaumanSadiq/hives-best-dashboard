param([string]$Artwork)
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Drawing
Add-Type -ReferencedAssemblies System.Drawing.Common,System.Drawing.Primitives,System.Runtime,System.Private.Windows.GdiPlus,System.Private.Windows.Core -TypeDefinition @'
using System;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Drawing.Imaging;
using System.IO;
public static class HiveArtwork {
    public static Bitmap Crop(Bitmap source, bool emblem) {
        int left=source.Width,top=source.Height,right=0,bottom=0;
        int limit=source.Height;
        if(emblem) {
            int runStart=0,run=0,longest=0;
            for(int y=(int)(source.Height*.45);y<(int)(source.Height*.8);y++) {
                bool empty=true;
                for(int x=0;x<source.Width;x++)if(source.GetPixel(x,y).A>32){empty=false;break;}
                if(empty){if(run==0)runStart=y;run++;if(run>longest){longest=run;limit=runStart;}}
                else run=0;
            }
            if(longest<10)throw new InvalidOperationException("Cannot identify the blank gap between emblem and wordmark.");
        }
        for(int y=0;y<limit;y++)for(int x=0;x<source.Width;x++) {
            Color c=source.GetPixel(x,y);
            if(c.A>32) { left=Math.Min(left,x);top=Math.Min(top,y);right=Math.Max(right,x);bottom=Math.Max(bottom,y); }
        }
        int pad=14;left=Math.Max(0,left-pad);top=Math.Max(0,top-pad);right=Math.Min(source.Width-1,right+pad);bottom=Math.Min(source.Height-1,bottom+pad);
        return source.Clone(new Rectangle(left,top,right-left+1,bottom-top+1),PixelFormat.Format32bppArgb);
    }
    public static byte[] Icon(Bitmap emblem,int size) {
        using(Bitmap image=new Bitmap(size,size)) {
            using(Graphics g=Graphics.FromImage(image)) {
                g.Clear(Color.Transparent);g.CompositingQuality=CompositingQuality.HighQuality;g.InterpolationMode=InterpolationMode.HighQualityBicubic;g.SmoothingMode=SmoothingMode.HighQuality;g.PixelOffsetMode=PixelOffsetMode.HighQuality;
                double scale=(size*.9)/Math.Max(emblem.Width,emblem.Height);
                int w=(int)Math.Round(emblem.Width*scale),h=(int)Math.Round(emblem.Height*scale);
                g.DrawImage(emblem,new Rectangle((size-w)/2,(size-h)/2,w,h));
            }
            using(MemoryStream stream=new MemoryStream()) {image.Save(stream,ImageFormat.Png);return stream.ToArray();}
        }
    }
    public static void Build(string input,string output) {
        Directory.CreateDirectory(output);
        using(Bitmap source=new Bitmap(input))using(Bitmap full=Crop(source,false))using(Bitmap emblem=Crop(source,true)) {
            if(source.GetPixel(0,0).A!=0)throw new InvalidOperationException("Logo source must have a transparent background.");
            full.Save(Path.Combine(output,"logo-full.png"),ImageFormat.Png);emblem.Save(Path.Combine(output,"logo-emblem.png"),ImageFormat.Png);
            int[] sizes={16,32,48,64,180,192,512};foreach(int size in sizes)File.WriteAllBytes(Path.Combine(output,"icon-"+size+".png"),Icon(emblem,size));
            using(BinaryWriter writer=new BinaryWriter(File.Create(Path.Combine(output,"favicon.ico")))) {
                int[] icoSizes={16,32,48};byte[][] png=new byte[3][];for(int i=0;i<3;i++)png[i]=Icon(emblem,icoSizes[i]);
                writer.Write((ushort)0);writer.Write((ushort)1);writer.Write((ushort)3);int offset=6+3*16;
                for(int i=0;i<3;i++){writer.Write((byte)icoSizes[i]);writer.Write((byte)icoSizes[i]);writer.Write((byte)0);writer.Write((byte)0);writer.Write((ushort)1);writer.Write((ushort)32);writer.Write(png[i].Length);writer.Write(offset);offset+=png[i].Length;}
                foreach(byte[] bytes in png)writer.Write(bytes);
            }
        }
    }
}
'@
$root=Split-Path -Parent $PSScriptRoot
$source=Join-Path $root 'resources\brand\official-logo-transparent.png'
New-Item -ItemType Directory -Path (Split-Path -Parent $source) -Force | Out-Null
if($Artwork){Copy-Item -LiteralPath $Artwork -Destination $source -Force}
if(-not (Test-Path -LiteralPath $source)){throw 'Official logo source is missing'}
$output=Join-Path $root 'public\images\brand'
[HiveArtwork]::Build($source,$output)
Copy-Item -LiteralPath (Join-Path $output 'favicon.ico') -Destination (Join-Path $root 'public\favicon.ico') -Force
Copy-Item -LiteralPath (Join-Path $output 'icon-180.png') -Destination (Join-Path $root 'public\apple-touch-icon.png') -Force
$iconData=[Convert]::ToBase64String([System.IO.File]::ReadAllBytes((Join-Path $output 'icon-64.png')))
[System.IO.File]::WriteAllText((Join-Path $root 'public\favicon.svg'),'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><image width="64" height="64" href="data:image/png;base64,'+$iconData+'"/></svg>')
Write-Output 'Cropped official full logo, emblem, favicon sizes and Apple touch icon prepared.'
