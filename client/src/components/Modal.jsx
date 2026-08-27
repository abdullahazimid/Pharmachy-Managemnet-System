export default function Modal({ title, onClose, children, wide }) {
  return (
    <div className="modal-overlay" onClick={onClose}>
      <div
        className={`modal-content ${wide ? 'modal-wide' : ''}`}
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-header">
          <h2>{title}</h2>
          <button type="button" className="close-btn" onClick={onClose}>
            &times;
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}
