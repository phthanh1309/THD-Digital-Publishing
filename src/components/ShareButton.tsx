"use client";

export default function ShareButton({
  url,
  title,
}: {
  url: string;
  title: string;
}) {
  async function handleClick() {
    if (navigator.share) {
      try {
        await navigator.share({ title, url });
        return;
      } catch {
        return;
      }
    }
    try {
      await navigator.clipboard.writeText(url);
      alert("Đã sao chép liên kết");
    } catch {
      window.prompt("Sao chép liên kết:", url);
    }
  }

  return (
    <button type="button" className="button button--secondary" onClick={handleClick}>
      Chia sẻ
    </button>
  );
}