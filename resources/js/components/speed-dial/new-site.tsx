type Props = {
    onClick(): void;
};

export default function NewSite({ onClick }: Props) {
    return (
        <div
            onClick={onClick}
            className="h-24 w-24 transform cursor-pointer content-center items-center overflow-hidden rounded-2xl bg-slate-700/20 text-center text-white shadow-md outline-dashed outline-4 outline-white backdrop-blur-sm transition-transform hover:scale-110 hover:shadow-xl"
        >
            <span>Add</span>
        </div>
    );
}
