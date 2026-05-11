import sys
import os
from faster_whisper import WhisperModel

def format_time(seconds):
    hours = int(seconds // 3600)
    minutes = int((seconds % 3600) // 60)
    secs = seconds % 60
    return f"{hours}:{minutes:02d}:{secs:05.2f}"

def generate_ass(segments, output_path):
    # Header with Robust Arial Style
    header = """[Script Info]
ScriptType: v4.00+
PlayResX: 1080
PlayResY: 1920
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Default,Arial,80,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,0,0,1,4,2,2,10,10,350,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""
    
    with open(output_path, 'w', encoding='utf-8') as f:
        f.write(header)
        for segment in segments:
            if not segment.words:
                continue
            for word in segment.words:
                start = format_time(word.start)
                end = format_time(word.end)
                text = word.word.strip().upper()
                
                # Dynamic Scale/Bounce + Highlight Yellow
                f.write(f"Dialogue: 0,{start},{end},Default,,0,0,0,,{{\\fscx120\\fscy120\\t(0,100,\\fscx100\\fscy100)\\c&H00FFFF&}}{text}\n")

def main():
    if len(sys.argv) < 3:
        print("Usage: python whisper_service.py <audio_path> <output_ass_path>")
        return

    audio_path = sys.argv[1]
    output_path = sys.argv[2]
    
    model_size = "small"
    try:
        model = WhisperModel(model_size, device="auto", compute_type="int8")
        segments, info = model.transcribe(audio_path, beam_size=5, word_timestamps=True, vad_filter=True)
        generate_ass(list(segments), output_path)
        print(f"Success: {output_path}")
    except Exception as e:
        print(f"Error: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()
